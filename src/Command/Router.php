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
use React\ChildProcess\Process;
use React\EventLoop\Loop;

class Router implements RouterInterface
{
    private readonly string $publicPath;

    private bool $isTestRunning = false;

    /** @var string[] */
    private array $failedTests = [];

    /** @var string[] */
    private array $lastFilters = [];

    // Track the currently running process so we can terminate it
    private ?Process $currentProcess = null;

    // Current run id for broadcasting stop events
    private ?string $currentRunId = null;

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

    public function onOpen(ConnectionInterface $conn, ?RequestInterface $request = null): void
    {
        $path = $request?->getUri()->getPath();
        $this->output->writeln('Request for ' . $path, OutputInterface::VERBOSITY_VERBOSE);

        if ($path === '/ws/status') {
            $this->httpServer->onOpen($conn, $request);
            return;
        }

        $this->handleHttpRequest($conn, $request);
    }

    private function handleHttpRequest(ConnectionInterface $connection, ?RequestInterface $request): void
    {
        $path = $request?->getUri()->getPath();
        $method = $request?->getMethod();
        $response = null;

        if ($path === '/api/tests' && $method === 'GET') {
            $tests = $this->testDiscoverer->discover();
            $jsonResponse = json_encode($tests);
            if ($jsonResponse === false) {
                $response = new GuzzleResponse(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Failed to encode tests to JSON.']));
            } else {
                $response = new GuzzleResponse(200, ['Content-Type' => 'application/json'], $jsonResponse);
            }
        } elseif (($path === '/api/run' || $path === '/api/run-failed') && $method === 'POST') {
            if ($this->isTestRunning) {
                $jsonResponse = json_encode(['error' => 'A test run is already in progress.']);
                if ($jsonResponse === false) {
                    $response = new GuzzleResponse(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Failed to encode error message to JSON.']));
                } else {
                    $response = new GuzzleResponse(429, ['Content-Type' => 'application/json'], $jsonResponse);
                }
            } else {
                /** @var string[] $filters */
                $filters = [];
                $isRerun = false;
                if ($path === '/api/run') {
                    $body = $request?->getBody()->getContents();
                    $payload = json_decode((string) $body, true) ?? [];
                    $filters = $payload['filters'] ?? [];
                    $this->lastFilters = $filters;
                } else { // /api/run-failed
                    $isRerun = true;
                    if ($this->failedTests === []) {
                        $jsonResponse = json_encode(['error' => 'No failed tests to run.']);
                        if ($jsonResponse === false) {
                            $response = new GuzzleResponse(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Failed to encode error message to JSON.']));
                        } else {
                            $response = new GuzzleResponse(400, ['Content-Type' => 'application/json'], $jsonResponse);
                        }

                        $this->sendResponse($connection, $response);
                        return;
                    }

                    $filters = $this->failedTests;
                }

                $this->runTests($filters, $isRerun);
                $jsonResponse = json_encode(['message' => 'Test run started.']);
                if ($jsonResponse === false) {
                    $response = new GuzzleResponse(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Failed to encode success message to JSON.']));
                } else {
                    $response = new GuzzleResponse(202, ['Content-Type' => 'application/json'], $jsonResponse);
                }
            }
        } elseif ($path === '/api/stop' && $method === 'POST') {
            // Handle stop requests
            $response = $this->stopTests();
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

    /**
     * @param string[] $filters
     */
    public function runTests(array $filters, bool $isRerun = false): void
    {
        if ($this->isTestRunning) {
            $this->output->writeln("<comment>Skipping test run: another is already in progress.</comment>");
            return;
        }

        $this->isTestRunning = true;
        $runId = Uuid::uuid4()->toString();
        $this->currentRunId = $runId;
        $junitLogfile = sys_get_temp_dir() . sprintf('/phpunit-gui-%s.xml', $runId);
        $this->output->writeln(sprintf('Starting test run #%s with filters: ', $runId) . implode(', ', $filters));

        $jsonBroadcast = json_encode(['type' => 'start', 'runId' => $runId]);
        if ($jsonBroadcast !== false) {
            $this->statusHandler->broadcast($jsonBroadcast);
        } else {
            $this->output->writeln('<error>Failed to encode start message to JSON for broadcast.</error>');
        }

        $process = $this->testRunner->run($junitLogfile, $filters);

        // Keep a reference so we can terminate later
        $this->currentProcess = $process;

        $process->stdout->on('data', function ($chunk) {
            $jsonBroadcast = json_encode(['type' => 'stdout', 'data' => $chunk]);
            if ($jsonBroadcast !== false) {
                $this->statusHandler->broadcast($jsonBroadcast);
            } else {
                $this->output->writeln('<error>Failed to encode stdout message to JSON for broadcast.</error>');
            }
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
                    // PHPStan knows 'suites' and 'testcases' always exist due to JUnitParser::parse return type
                    foreach ($parsedResults['suites'] as $suite) {
                        foreach ($suite['testcases'] as $testcase) {
                            if (in_array($testcase['status'], ['failed', 'error'])) {
                                $currentRunFailedTests[] = $testcase['class'] . '::' . $testcase['name'];
                            }
                        }
                    }

                    $this->failedTests = $currentRunFailedTests;
                }

                unlink($junitLogfile);
            }

            $jsonBroadcast = json_encode([
                'type' => 'exit',
                'runId' => $runId,
                'exitCode' => $exitCode,
                'results' => $parsedResults,
            ]);

            if ($jsonBroadcast !== false) {
                $this->statusHandler->broadcast($jsonBroadcast);
            } else {
                $this->output->writeln('<error>Failed to encode exit message to JSON for broadcast.</error>');
            }

            if ($exitCode) {
                $this->notify($exitCode, $parsedResults, $runId);
            }

            // Clear running process/runId state
            $this->currentProcess = null;
            $this->currentRunId = null;
            $this->isTestRunning = false;
        });
    }

    /**
     * @return string[]
     */
    public function getLastFilters(): array
    {
        return $this->lastFilters;
    }

    /**
     * Attempt to stop the currently running test process.
     */
    private function stopTests(): GuzzleResponse
    {
        if (!$this->isTestRunning || !$this->currentProcess instanceof \React\ChildProcess\Process) {
            $json = json_encode(['error' => 'No test run in progress.']);
            if ($json === false) {
                return new GuzzleResponse(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Failed to encode error message to JSON.']));
            }

            return new GuzzleResponse(400, ['Content-Type' => 'application/json'], $json);
        }

        try {
            $this->currentProcess->terminate();
        } catch (Exception $exception) {
            $this->output->writeln(sprintf('<error>Failed to terminate process: %s</error>', $exception->getMessage()));
            $json = json_encode(['error' => 'Failed to terminate running tests.']);
            if ($json === false) {
                return new GuzzleResponse(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Failed to encode error message to JSON.']));
            }

            return new GuzzleResponse(500, ['Content-Type' => 'application/json'], $json);
        }

        // Broadcast a stopped message immediately so the UI can react quickly
        $jsonBroadcast = json_encode(['type' => 'stopped', 'runId' => $this->currentRunId]);
        if ($jsonBroadcast !== false) {
            $this->statusHandler->broadcast($jsonBroadcast);
        }

        // If the process doesn't exit within a short timeout, force-kill it (SIGKILL)
        try {
            $timeoutSeconds = 2.0;
            Loop::get()->addTimer($timeoutSeconds, function () {
                // If still running, force kill
                if ($this->currentProcess instanceof \React\ChildProcess\Process && $this->currentProcess->isRunning()) {
                    $sigkill = defined('SIGKILL') ? SIGKILL : 9;
                    try {
                        $this->currentProcess->terminate($sigkill);
                        $jsonBroadcast = json_encode(['type' => 'stopped', 'runId' => $this->currentRunId, 'forced' => true]);
                        if ($jsonBroadcast !== false) {
                            $this->statusHandler->broadcast($jsonBroadcast);
                        }
                    } catch (Exception $e) {
                        $this->output->writeln(sprintf('<error>Failed to force-kill process: %s</error>', $e->getMessage()));
                    }
                }
            });
        } catch (Exception $exception) {
            // If scheduling the timer fails for any reason, just log it and continue
            $this->output->writeln(sprintf('<error>Failed to schedule force-kill timer: %s</error>', $exception->getMessage()));
        }

        $json = json_encode(['message' => 'Stop requested.']);
        if ($json === false) {
            return new GuzzleResponse(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Failed to encode success message to JSON.']));
        }

        return new GuzzleResponse(202, ['Content-Type' => 'application/json'], $json);
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

    /**
     * @param array{
     *      suites: array<array{
     *          name: string,
     *          tests: int,
     *          assertions: int,
     *          failures: int,
     *          errors: int,
     *          time: float,
     *          testcases: array<array{
     *              name: string,
     *              class: string,
     *              file: string,
     *              line: int,
     *              assertions: int,
     *              time: float,
     *              status: string,
     *              failure: ?array{type: string, message: string},
     *              error: ?array{type: string, message: string}
     *          }>
     *      }>,
     *      summary: array{
     *          tests: int,
     *          assertions: int,
     *          failures: int,
     *          errors: int,
     *          time: float
     *      }
     *  } $parsedResults
     */
    private function notify(int $exitCode, ?array $parsedResults, string $runId): void
    {
        $notificationTitle = 'PHPUnit Hub Test Results';

        if ($exitCode === 0) {
            $notificationMessage = 'All tests passed successfully!';
        } else {
            $failures = 0;
            $errors = 0;
            if ($parsedResults !== null) {
                foreach ($parsedResults['suites'] as $suite) {
                    foreach ($suite['testcases'] as $testcase) {
                        if ($testcase['status'] === 'failed') {
                            $failures++;
                        } elseif ($testcase['status'] === 'error') {
                            $errors++;
                        }
                    }
                }
            }

            $notificationMessage = sprintf('Tests finished with %d failures and %d errors.', $failures, $errors);
        }

        $command = sprintf('notify-send "%s" "%s"', $notificationTitle, $notificationMessage);

        $notificationProcess = new Process($command);
        $notificationProcess->start(Loop::get()); // Usa il loop di eventi predefinito

        $notificationProcess->on('exit', function ($code) use ($runId, $notificationTitle, $notificationMessage, $command) {
            if ($code !== 0) {
                $this->output->writeln(sprintf('<error>Failed to send notification for run #%s. Command: "%s", Exit Code: %s</error>', $runId, $command, $code));
            } else {
                $this->output->writeln(sprintf('<info>Notification sent for run #%s: "%s - %s"</info>', $runId, $notificationTitle, $notificationMessage));
            }
        });
    }
}
