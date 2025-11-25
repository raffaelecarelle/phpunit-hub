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
use Ratchet\Http\HttpServerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Router implements RouterInterface
{
    private readonly string $publicPath;

    private bool $isTestRunning = false;

    private array $failedTests = [];

    private array $lastFilters = [];

    public function __construct(
        private readonly HttpServerInterface $httpServer,
        private readonly OutputInterface $output,
        private readonly StatusHandler $statusHandler,
        private readonly TestRunner $testRunner,
        private readonly JUnitParser $jUnitParser,
        private readonly TestDiscoverer $testDiscoverer
    ) {
        $this->publicPath = dirname(__DIR__, 2) . '/public';
    }

    public function onOpen(ConnectionInterface $conn, RequestInterface $request = null): void
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

        if (!$response instanceof GuzzleResponse) {
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
                try {
                    $parsedResults = $this->jUnitParser->parse($xmlContent);
                } catch (Exception $e) {
                    $this->output->writeln(sprintf('<error>Error parsing JUnit XML: %s</error>', $e->getMessage()));
                }

                if (!$isRerun && $parsedResults !== null) {
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

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $this->httpServer->onMessage($from, $msg);
    }

    public function onClose(ConnectionInterface $conn): void
    {
        if (property_exists($conn, 'WebSocket') && $conn->WebSocket !== null) {
            $this->httpServer->onClose($conn);
        }
    }

    public function onError(ConnectionInterface $conn, Exception $e): void
    {
        if (property_exists($conn, 'WebSocket') && $conn->WebSocket !== null) {
            $this->httpServer->onError($conn, $e);
        } else {
            $this->output->writeln(sprintf('<error>HTTP Server error: %s</error>', $e->getMessage()));
            $conn->close();
        }
    }
}
