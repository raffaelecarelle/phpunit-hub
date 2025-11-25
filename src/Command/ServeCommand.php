<?php

namespace PHPUnitGUI\Command;

use Exception;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnitGUI\Discoverer\TestDiscoverer;
use PHPUnitGUI\Parser\JUnitParser;
use PHPUnitGUI\TestRunner\TestRunner;
use PHPUnitGUI\WebSocket\StatusHandler;
use Psr\Http\Message\RequestInterface;
use Ramsey\Uuid\Uuid;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Http\HttpServerInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\Socket\SocketServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ServeCommand extends Command
{
    protected static $defaultName = 'serve';

    protected function configure(): void
    {
        $this->setDescription('Starts the PHPUnit GUI server.')
            ->addOption('watch', 'w', InputOption::VALUE_NONE, 'Enable file watching to re-run tests on changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $port = 8080;
        $watch = $input->getOption('watch');

        $loop = Loop::get();
        $testRunner = new TestRunner($loop);
        $statusHandler = new StatusHandler();
        $parser = new JUnitParser();
        $discoverer = new TestDiscoverer(getcwd());
        $wsServer = new WsServer($statusHandler);

        $router = new class ($wsServer, $output, $statusHandler, $testRunner, $parser, $discoverer) implements HttpServerInterface {
            private readonly string $publicPath;

            private bool $isTestRunning = false;

            private array $failedTests = [];

            private array $lastFilters = [];

            public function __construct(private readonly HttpServerInterface $httpServer, private readonly OutputInterface $output, private readonly StatusHandler $statusHandler, private readonly TestRunner $testRunner, private readonly JUnitParser $jUnitParser, private readonly TestDiscoverer $testDiscoverer)
            {
                $this->publicPath = dirname(__DIR__, 2) . '/public';
            }

            public function onOpen(ConnectionInterface $conn, RequestInterface $request = null)
            {
                $path = $request?->getUri()->getPath();
                $this->output->writeln('Request for ' . $path, OutputInterface::VERBOSITY_VERBOSE);

                if ($path === '/ws/status') {
                    $this->httpServer->onOpen($conn, $request);
                    return;
                }

                $this->handleHttpRequest($conn, $request);
            }

            private function handleHttpRequest(ConnectionInterface $connection, RequestInterface $request): void
            {
                $path = $request->getUri()->getPath();
                $method = $request->getMethod();
                $response = null;

                if ($path === '/api/tests' && $method === 'GET') {
                    $tests = $this->testDiscoverer->discover();
                    $response = new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode($tests));
                } elseif (($path === '/api/run' || $path === '/api/run-failed') && $method === 'POST') {
                    if ($this->isTestRunning) {
                        $response = new GuzzleResponse(429, ['Content-Type' => 'application/json'], json_encode(['error' => 'A test run is already in progress.']));
                    } else {
                        $filters = [];
                        $isRerun = false;
                        if ($path === '/api/run') {
                            $body = $request->getBody()->getContents();
                            $payload = json_decode($body, true) ?? [];
                            $filters = $payload['filters'] ?? [];
                            $this->lastFilters = $filters;
                        } else { // /api/run-failed
                            $isRerun = true;
                            if ($this->failedTests === []) {
                                $response = new GuzzleResponse(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'No failed tests to run.']));
                                $this->sendResponse($connection, $response);
                                return;
                            }

                            $filters = $this->failedTests;
                        }

                        $this->runTests($filters, $isRerun);
                        $response = new GuzzleResponse(202, ['Content-Type' => 'application/json'], json_encode(['message' => 'Test run started.']));
                    }
                } else {
                    $filePath = $this->publicPath . $path;
                    if ($path === '/') {
                        $filePath = $this->publicPath . '/index.html';
                    }

                    if (file_exists($filePath) && is_file($filePath) && str_starts_with(realpath($filePath), $this->publicPath)) {
                        $response = new GuzzleResponse(200, ['Content-Type' => $this->getMimeType($filePath)], file_get_contents($filePath));
                    }
                }

                if (!$response instanceof \GuzzleHttp\Psr7\Response) {
                    $response = new GuzzleResponse(404, ['Content-Type' => 'text/plain'], 'Not Found');
                }

                $this->sendResponse($connection, $response);
            }

            public function runTests(array $filters, bool $isRerun = false): void
            {
                if ($this->isTestRunning) {
                    $this->output->writeln("<comment>Skipping test run: another is already in progress.</comment>");
                    return;
                }

                $this->isTestRunning = true;
                $runId = Uuid::uuid4()->toString();
                $junitLogfile = sys_get_temp_dir() . sprintf('/phpunit-gui-%s.xml', $runId);
                $this->output->writeln(sprintf('Starting test run #%s with filters: ', $runId) . implode(', ', $filters));

                $this->statusHandler->broadcast(json_encode(['type' => 'start', 'runId' => $runId]));
                $process = $this->testRunner->run($junitLogfile, $filters);

                $process->stdout->on('data', function ($chunk) {
                    $this->statusHandler->broadcast(json_encode(['type' => 'stdout', 'data' => $chunk]));
                });

                $process->on('exit', function ($exitCode) use ($runId, $junitLogfile, $isRerun) {
                    $this->output->writeln(sprintf('Test run #%s finished with code %s.', $runId, $exitCode));
                    $parsedResults = null;
                    if (file_exists($junitLogfile)) {
                        $xmlContent = file_get_contents($junitLogfile);
                        $parsedResults = $this->jUnitParser->parse($xmlContent);

                        if (!$isRerun) {
                            $currentRunFailedTests = [];
                            if (isset($parsedResults['suites'])) {
                                foreach ($parsedResults['suites'] as $suite) {
                                    if (isset($suite['testcases'])) {
                                        foreach ($suite['testcases'] as $testcase) {
                                            if (in_array($testcase['status'], ['failed', 'error'])) {
                                                $currentRunFailedTests[] = $testcase['class'] . '::' . $testcase['name'];
                                            }
                                        }
                                    }
                                }
                            }

                            $this->failedTests = $currentRunFailedTests;
                        }

                        unlink($junitLogfile);
                    }

                    $this->statusHandler->broadcast(json_encode([
                        'type' => 'exit',
                        'runId' => $runId,
                        'exitCode' => $exitCode,
                        'results' => $parsedResults,
                    ]));

                    $this->isTestRunning = false;
                });
            }

            public function getLastFilters(): array
            {
                return $this->lastFilters;
            }

            private function sendResponse(ConnectionInterface $connection, GuzzleResponse $guzzleResponse): void
            {
                $connection->send(Message::toString($guzzleResponse));
                $connection->close();
            }

            private function getMimeType(string $filename): string
            {
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                return match ($extension) {
                    'html' => 'text/html; charset=utf-8',
                    'css' => 'text/css; charset=utf-8',
                    'js' => 'application/javascript; charset=utf-8',
                    default => 'text/plain',
                };
            }

            public function onMessage(ConnectionInterface $from, $msg)
            {
                $this->httpServer->onMessage($from, $msg);
            }

            public function onClose(ConnectionInterface $conn)
            {
                if (property_exists($conn, 'WebSocket') && $conn->WebSocket !== null) {
                    $this->httpServer->onClose($conn);
                }
            }

            public function onError(ConnectionInterface $conn, Exception $e)
            {
                if (property_exists($conn, 'WebSocket') && $conn->WebSocket !== null) {
                    $this->httpServer->onError($conn, $e);
                } else {
                    $this->output->writeln(sprintf('<error>HTTP Server error: %s</error>', $e->getMessage()));
                    $conn->close();
                }
            }
        };

        $httpServer = new HttpServer($router);
        $socketServer = new SocketServer('127.0.0.1:' . $port, [], $loop);
        $ioServer = new IoServer($httpServer, $socketServer, $loop);

        if ($watch) {
            $this->startFileWatcher($loop, $output, $router);
        }

        $output->writeln(sprintf('<info>Starting server on http://127.0.0.1:%d</info>', $port));
        $output->writeln("API endpoint available at GET /api/tests");
        $output->writeln("API endpoint available at POST /api/run");
        $output->writeln("API endpoint available at POST /api/run-failed");
        $output->writeln("WebSocket server listening on /ws/status");
        $output->writeln("Serving static files from 'public' directory");

        $ioServer->run();

        return Command::SUCCESS;
    }

    private function startFileWatcher($loop, OutputInterface $output, $router): void
    {
        // Check for inotifywait availability
        $checkProcess = new Process('command -v inotifywait');
        $checkProcess->start($loop);

        $checkProcess->on('exit', function ($exitCode) use ($loop, $output, $router) {
            if ($exitCode !== 0) {
                $output->writeln('<error>Error: `inotifywait` command not found.</error>');
                $output->writeln('<error>Please install `inotify-tools` to use the --watch feature on Linux.</error>');
                $output->writeln('<error>Example: sudo apt-get install inotify-tools</error>');
                return;
            }

            $output->writeln('<info>Event-based file watcher enabled (inotify).</info>');

            // Dynamically find paths from composer.json
            $watchPaths = [];
            $composerJsonPath = getcwd() . '/composer.json';

            if (file_exists($composerJsonPath)) {
                $composerConfig = json_decode(file_get_contents($composerJsonPath), true);
                $psr4Paths = array_merge(
                    $composerConfig['autoload']['psr-4'] ?? [],
                    $composerConfig['autoload-dev']['psr-4'] ?? []
                );

                foreach ($psr4Paths as $psr4Path) {
                    if (is_array($psr4Path)) {
                        $watchPaths = array_merge($watchPaths, $psr4Path);
                    } else {
                        $watchPaths[] = $psr4Path;
                    }
                }
            }

            // Fallback to default if composer.json is not found or doesn't have paths
            if ($watchPaths === []) {
                $watchPaths = ['src', 'tests'];
            }

            $absolutePaths = array_map(fn ($path) => getcwd() . '/' . trim($path, '/\\'), $watchPaths);
            $uniquePaths = array_unique($absolutePaths);
            $existingWatchPaths = array_filter($uniquePaths, is_dir(...));

            if ($existingWatchPaths === []) {
                $output->writeln('<warning>Could not find any valid directories to watch from composer.json or defaults.</warning>');
                return;
            }

            $command = sprintf(
                'inotifywait -q -m -r -e modify,create,delete,move --format "%%e %%w%%f" %s',
                implode(' ', array_map(escapeshellarg(...), $existingWatchPaths))
            );

            $watcherProcess = new Process($command);
            $watcherProcess->start($loop);

            $debounceTimer = null;

            $watcherProcess->stdout->on('data', function ($chunk) use ($output, $loop, $router, &$debounceTimer) {
                $lines = explode("\n", trim($chunk));
                foreach ($lines as $line) {
                    if ($line === '') {
                        continue;
                    }

                    if ($line === '0') {
                        continue;
                    }

                    if (!str_ends_with(strtolower($line), '.php')) {
                        continue;
                    }

                    $output->writeln(sprintf('<comment>File change detected: %s</comment>', $line));
                }

                if ($debounceTimer !== null) {
                    $loop->cancelTimer($debounceTimer);
                }

                $debounceTimer = $loop->addTimer(0.5, function () use ($router, $output) {
                    $output->writeln('<info>Re-running tests due to file changes...</info>');
                    $router->runTests($router->getLastFilters());
                });
            });

            $watcherProcess->stderr->on('data', function ($chunk) use ($output) {
                $output->writeln(sprintf('<error>Watcher error: %s</error>', $chunk));
            });

            foreach ($existingWatchPaths as $existingWatchPath) {
                $output->writeln(sprintf('<info>Watching for changes in %s</info>', $existingWatchPath));
            }
        });
    }
}
