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
use React\EventLoop\Loop;
use React\Socket\SocketServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ServeCommand extends Command
{
    protected static $defaultName = 'serve';

    protected function configure(): void
    {
        $this->setDescription('Starts the PHPUnit GUI server.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $port = 8080;

        $loop = Loop::get();
        $testRunner = new TestRunner($loop);
        $statusHandler = new StatusHandler();
        $parser = new JUnitParser();
        $discoverer = new TestDiscoverer(getcwd());
        $wsServer = new WsServer($statusHandler);

        $router = new class($wsServer, $output, $statusHandler, $testRunner, $parser, $discoverer) implements HttpServerInterface {
            private HttpServerInterface $wsServer;
            private OutputInterface $output;
            private StatusHandler $statusHandler;
            private TestRunner $testRunner;
            private JUnitParser $parser;
            private TestDiscoverer $discoverer;
            private string $publicPath;
            private bool $isTestRunning = false;

            public function __construct(HttpServerInterface $wsServer, OutputInterface $output, StatusHandler $statusHandler, TestRunner $testRunner, JUnitParser $parser, TestDiscoverer $discoverer)
            {
                $this->wsServer = $wsServer;
                $this->output = $output;
                $this->statusHandler = $statusHandler;
                $this->testRunner = $testRunner;
                $this->parser = $parser;
                $this->discoverer = $discoverer;
                $this->publicPath = dirname(__DIR__, 2) . '/public';
            }

            public function onOpen(ConnectionInterface $conn, RequestInterface $request = null)
            {
                $path = $request?->getUri()->getPath();
                $this->output->writeln("Request for {$path}", OutputInterface::VERBOSITY_VERBOSE);

                if ($path === '/ws/status') {
                    $this->wsServer->onOpen($conn, $request);
                    return;
                }

                $this->handleHttpRequest($conn, $request);
            }

            private function handleHttpRequest(ConnectionInterface $conn, RequestInterface $request): void
            {
                $path = $request->getUri()->getPath();
                $method = $request->getMethod();
                $response = null;

                if ($path === '/api/tests' && $method === 'GET') {
                    $tests = $this->discoverer->discover();
                    $response = new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode($tests));
                } elseif ($path === '/api/run' && $method === 'POST') {
                    if ($this->isTestRunning) {
                        $response = new GuzzleResponse(429, ['Content-Type' => 'application/json'], json_encode(['error' => 'A test run is already in progress.']));
                    } else {
                        $body = $request->getBody()->getContents();
                        $payload = json_decode($body, true) ?? [];
                        $filters = $payload['filters'] ?? [];
                        $group = $payload['group'] ?? ''; // New: --group filter
                        $suites = $payload['suites'] ?? [];
                        $options = $payload['options'] ?? [];

                        $this->isTestRunning = true;
                        $runId = Uuid::uuid4()->toString();
                        $junitLogfile = sys_get_temp_dir() . "/phpunit-gui-{$runId}.xml";
                        $this->output->writeln("Starting test run #{$runId}...");

                        $this->statusHandler->broadcast(json_encode(['type' => 'start', 'runId' => $runId]));
                        $process = $this->testRunner->run($junitLogfile, $filters, $group, $suites, $options);

                        $process->stdout->on('data', function ($chunk) {
                            $this->statusHandler->broadcast(json_encode(['type' => 'stdout', 'data' => $chunk]));
                        });

                        $process->on('exit', function ($exitCode) use ($runId, $junitLogfile) {
                            $this->output->writeln("Test run #{$runId} finished with code {$exitCode}.");
                            $parsedResults = null;
                            if (file_exists($junitLogfile)) {
                                $xmlContent = file_get_contents($junitLogfile);
                                $parsedResults = $this->parser->parse($xmlContent);
                                unlink($junitLogfile);
                            }

                            $this->statusHandler->broadcast(json_encode([
                                'type' => 'exit',
                                'runId' => $runId,
                                'exitCode' => $exitCode,
                                'results' => $parsedResults
                            ]));

                            $this->isTestRunning = false;
                        });

                        $response = new GuzzleResponse(202, ['Content-Type' => 'application/json'], json_encode(['runId' => $runId, 'message' => 'Test run started.']));
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

                if ($response === null) {
                    $response = new GuzzleResponse(404, ['Content-Type' => 'text/plain'], 'Not Found');
                }

                $this->sendResponse($conn, $response);
            }

            private function sendResponse(ConnectionInterface $conn, GuzzleResponse $response): void
            {
                $conn->send(Message::toString($response));
                $conn->close();
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
                $this->wsServer->onMessage($from, $msg);
            }

            public function onClose(ConnectionInterface $conn)
            {
                if (isset($conn->WebSocket)) {
                    $this->wsServer->onClose($conn);
                }
            }

            public function onError(ConnectionInterface $conn, Exception $e)
            {
                if (isset($conn->WebSocket)) {
                    $this->wsServer->onError($conn, $e);
                } else {
                    $this->output->writeln("<error>HTTP Server error: {$e->getMessage()}</error>");
                    $conn->close();
                }
            }
        };

        $httpServer = new HttpServer($router);
        $socket = new SocketServer("127.0.0.1:{$port}", [], $loop);
        $server = new IoServer($httpServer, $socket, $loop);

        $output->writeln("<info>Starting server on http://127.0.0.1:{$port}</info>");
        $output->writeln("API endpoint available at GET /api/tests");
        $output->writeln("API endpoint available at POST /api/run");
        $output->writeln("WebSocket server listening on /ws/status");
        $output->writeln("Serving static files from 'public' directory");

        $server->run();

        return Command::SUCCESS;
    }
}
