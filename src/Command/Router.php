<?php

namespace PhpUnitHub\Command;

use Exception;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PhpUnitHub\Discoverer\TestDiscoverer;
use PhpUnitHub\TestRunner\TestRunner;
use PhpUnitHub\WebSocket\StatusHandler;
use Psr\Http\Message\RequestInterface;
use Ramsey\Uuid\Uuid;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use React\ChildProcess\Process;
use React\EventLoop\Loop;

use function defined;
use function dirname;
use function file_exists;
use function file_get_contents;
use function filemtime;
use function hash_file;
use function implode;
use function in_array;
use function is_file;
use function json_decode;
use function json_encode;
use function pathinfo;
use function property_exists;
use function realpath;
use function sprintf;
use function str_replace;
use function sys_get_temp_dir;
use function unlink;
use function uniqid;

class Router implements RouterInterface
{
    private readonly string $publicPath;

    /** @var array<string, Process> */
    private array $runningProcesses = [];

    /** @var array<string, array<string, mixed>> */ // Mappa runId a lastFilters, lastSuites, lastGroups, lastOptions per ogni runId
    private array $runContexts = [];

    /** @var array<string, array<string, mixed>> */ // Mappa runId a summary
    private array $lastRunSummaries = [];

    /** @var string[] */
    private array $failedTests = [];

    /** @var string[] */
    private array $lastFilters = [];

    /** @var string[] */
    private array $lastSuites = [];

    /** @var string[] */
    private array $lastGroups = [];

    /** @var array<string, bool> */
    private array $lastOptions = [];

    public function __construct(
        private readonly HttpServerInterface $httpServer,
        private readonly OutputInterface $output,
        private readonly StatusHandler $statusHandler,
        private readonly TestRunner $testRunner,
        private readonly TestDiscoverer $testDiscoverer,
        private readonly string $host = '127.0.0.1',
        private readonly string $port = '8080'
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
            $groups = [];
            /** @var string[] $suites */
            $suites = [];
            /** @var array<string, bool> $options */
            $options = [];
            $isRerun = false;

            if ($path === '/api/run') {
                $body = $request?->getBody()->getContents();
                $payload = json_decode((string) $body, true) ?? [];
                $filters = $payload['filters'] ?? [];
                $groups = $payload['groups'] ?? [];
                $suites = $payload['suites'] ?? [];
                $options = $payload['options'] ?? [];
                $contextId = $payload['contextId'] ?? null; // Get contextId from payload
                $this->lastFilters = $filters;
                $this->lastSuites = $suites;
                $this->lastGroups = $groups;
                $this->lastOptions = $options;
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
                // For run-failed, we might not have a specific contextId, or it could be 'failed'
                $body = $request?->getBody()->getContents();
                $payload = json_decode((string) $body, true) ?? [];
                $contextId = $payload['contextId'] ?? 'failed';
            }

            $runId = $this->runTests($filters, $suites, $groups, $options, $isRerun, $contextId);
            $jsonResponse = json_encode(['message' => 'Test run started.', 'runId' => $runId]);
            if ($jsonResponse === false) {
                $response = new GuzzleResponse(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Failed to encode success message to JSON.']));
            } else {
                $response = new GuzzleResponse(202, ['Content-Type' => 'application/json'], $jsonResponse);
            }
        } elseif ($path === '/api/stop' && $method === 'POST') {
            // Handle stop requests for all running tests
            $response = $this->stopAllTests();
        } elseif (str_starts_with((string) $path, '/api/stop-single-test/') && $method === 'POST') {
            $parts = explode('/', (string) $path);
            $runId = end($parts);
            $response = $this->stopSingleTest($runId);
        } else {
            $filePath = $this->publicPath . $path;
            if ($path === '/') {
                $filePath = $this->publicPath . '/index.html';
            }

            if (file_exists($filePath) && is_file($filePath) && str_starts_with(realpath($filePath), $this->publicPath)) {
                $content = file_get_contents($filePath);
                if ($path === '/' || $path === '/index.html') {
                    $cssPath = $this->publicPath . '/css/styles.css';
                    $cssVersion = file_exists($cssPath) ? hash_file('md5', $cssPath) : 'dev';
                    $content = str_replace(
                        ['{{ws_host}}', '{{ws_port}}', '{{css_version}}'],
                        [$this->host, $this->port, $cssVersion],
                        $content
                    );
                }

                $response = new GuzzleResponse(200, ['Content-Type' => $this->getMimeType($filePath)], $content);
            }
        }

        if (!$response instanceof GuzzleResponse) {
            $response = new GuzzleResponse(404, ['Content-Type' => 'text/plain'], 'Not Found');
        }

        $this->sendResponse($connection, $response);
    }

    /**
     * @param string[] $filters
     * @param string[] $suites
     * @param string[] $groups
     * @param array<string, bool> $options
     * @param string|null $contextId An identifier from the frontend to associate this run with a specific UI element.
     * @return string The runId of the started test process.
     */
    public function runTests(array $filters, array $suites = [], array $groups = [], array $options = [], bool $isRerun = false, ?string $contextId = null): string
    {
        $runId = Uuid::uuid4()->toString();

        $this->runContexts[$runId] = [
            'filters' => $filters,
            'suites' => $suites,
            'groups' => $groups,
            'options' => $options,
            'contextId' => $contextId, // Store contextId
        ];

        $this->output->writeln(sprintf('Starting test run #%s with filters: ', $runId) . implode(', ', $filters));

        $jsonBroadcast = json_encode(['type' => 'start', 'runId' => $runId, 'contextId' => $contextId]); // Include contextId
        if ($jsonBroadcast !== false) {
            $this->statusHandler->broadcast($jsonBroadcast);
        } else {
            $this->output->writeln('<error>Failed to encode start message to JSON for broadcast.</error>');
        }

        $process = $this->testRunner->run($filters, $groups, $suites, $options);

        // Keep a reference so we can terminate later
        $this->runningProcesses[$runId] = $process;

        // Debug: Listen to STDOUT to see normal output
        $process->stdout->on('data', function ($chunk) use ($runId) {
            $this->output->writeln(sprintf('[DEBUG STDOUT] Run %s: %s', $runId, trim($chunk)));
        });

        // Listen to STDERR for realtime events
        $buffer = '';

        $process->stderr->on('data', function ($chunk) use (&$buffer, $runId) {
            $this->output->writeln(sprintf('[DEBUG] Received STDERR chunk for run %s: %s bytes', $runId, strlen($chunk)));

            $buffer .= $chunk;
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines); // Keep the last (incomplete) line in the buffer

            $this->output->writeln(sprintf('[DEBUG] Processing %d lines from STDERR', count($lines)));

            foreach ($lines as $line) {
                if ($line === '') {
                    continue;
                }

                if ($line === '0') {
                    continue;
                }

                $this->output->writeln(sprintf('[DEBUG] Processing line: %s', substr($line, 0, 100)));

                // Each line is a JSON object from our RealtimeTestExtension
                $decodedLine = json_decode($line, true);
                if ($decodedLine === null) {
                    $this->output->writeln(sprintf('<error>Failed to decode JSON from realtime output: %s</error>', $line));
                    continue;
                }

                $this->output->writeln(sprintf('[DEBUG] Decoded event: %s', $decodedLine['event'] ?? 'unknown'));

                // Store the summary if it's the execution.ended event
                if ($decodedLine['event'] === 'execution.ended') {
                    $this->lastRunSummaries[$runId] = $decodedLine['data']['summary'];
                }

                $jsonBroadcast = json_encode(['type' => 'realtime', 'runId' => $runId, 'data' => $line]);
                if ($jsonBroadcast !== false) {
                    $this->output->writeln('[DEBUG] Broadcasting realtime event');
                    $this->statusHandler->broadcast($jsonBroadcast);
                } else {
                    $this->output->writeln('<error>Failed to encode realtime message to JSON for broadcast.</error>');
                }
            }
        });

        $process->on('exit', function ($exitCode) use ($runId, $isRerun, $contextId, &$buffer) { // Pass contextId to closure
            $this->output->writeln(sprintf('Test run #%s finished with code %s.', $runId, $exitCode));

            // Ensure all buffered data is processed
            if ($buffer !== '' && $buffer !== '0') {
                $lines = explode("\n", $buffer);
                foreach ($lines as $line) {
                    if ($line === '') {
                        continue;
                    }

                    if ($line === '0') {
                        continue;
                    }

                    $decodedLine = json_decode($line, true);
                    if ($decodedLine === null) {
                        $this->output->writeln(sprintf('<error>Failed to decode JSON from final realtime output: %s</error>', $line));
                        continue;
                    }

                    if ($decodedLine['event'] === 'execution.ended') {
                        $this->lastRunSummaries[$runId] = $decodedLine['data']['summary'];
                    }

                    $jsonBroadcast = json_encode(['type' => 'realtime', 'runId' => $runId, 'data' => $line]);
                    if ($jsonBroadcast !== false) {
                        $this->statusHandler->broadcast($jsonBroadcast);
                    } else {
                        $this->output->writeln('<error>Failed to encode final realtime message to JSON for broadcast.</error>');
                    }
                }
            }

            // Update failedTests based on the summary
            $summary = $this->lastRunSummaries[$runId] ?? null;
            if ($summary !== null && !$isRerun) {
                if (($summary['numberOfFailures'] ?? 0) > 0 || ($summary['numberOfErrors'] ?? 0) > 0) {
                    // If there are failures/errors, we need to collect the actual failed test names
                    // This would require parsing all 'test.failed' and 'test.errored' events
                    // For now, we'll just assume if there are failures, we don't clear the list
                    // A more robust solution would involve collecting failed test IDs during the run
                } else {
                    $this->failedTests = []; // Clear if all passed
                }
            }

            $jsonBroadcast = json_encode([
                'type' => 'exit',
                'runId' => $runId,
                'exitCode' => $exitCode,
                'contextId' => $contextId, // Include contextId in exit message
            ]);

            if ($jsonBroadcast !== false) {
                $this->statusHandler->broadcast($jsonBroadcast);
            } else {
                $this->output->writeln('<error>Failed to encode exit message to JSON for broadcast.</error>');
            }

            if ($exitCode) {
                $this->notify($exitCode, $summary, $runId);
            }

            // Clear running process/runId state
            unset($this->runningProcesses[$runId], $this->runContexts[$runId], $this->lastRunSummaries[$runId]);
        });

        return $runId;
    }

    public function getLastFilters(): array
    {
        // This now refers to the last global run, not a specific single test run
        return $this->lastFilters;
    }

    public function getLastGroups(): array
    {
        // This now refers to the last global run, not a specific single test run
        return $this->lastGroups;
    }

    public function getLastOptions(): array
    {
        // This now refers to the last global run, not a specific single test run
        return $this->lastOptions;
    }

    public function getLastSuites(): array
    {
        // This now refers to the last global run, not a specific single test run
        return $this->lastSuites;
    }

    /**
     * Attempt to stop all currently running test processes.
     */
    private function stopAllTests(): GuzzleResponse
    {
        if ($this->runningProcesses === []) {
            $json = json_encode(['error' => 'No test run in progress.']);
            if ($json === false) {
                return new GuzzleResponse(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Failed to encode error message to JSON.']));
            }

            return new GuzzleResponse(400, ['Content-Type' => 'application/json'], $json);
        }

        foreach (array_keys($this->runningProcesses) as $runId) {
            $this->terminateProcess($runId);
        }

        $json = json_encode(['message' => 'Stop requested for all running tests.']);
        if ($json === false) {
            return new GuzzleResponse(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Failed to encode success message to JSON.']));
        }

        return new GuzzleResponse(202, ['Content-Type' => 'application/json'], $json);
    }

    /**
     * Attempt to stop a specific running test process.
     */
    private function stopSingleTest(string $runId): GuzzleResponse
    {
        if (!isset($this->runningProcesses[$runId])) {
            $json = json_encode(['error' => sprintf('No test run found with ID %s.', $runId)]);
            if ($json === false) {
                return new GuzzleResponse(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Failed to encode error message to JSON.']));
            }

            return new GuzzleResponse(404, ['Content-Type' => 'application/json'], $json);
        }

        $this->terminateProcess($runId);

        $json = json_encode(['message' => sprintf('Stop requested for test run %s.', $runId)]);
        if ($json === false) {
            return new GuzzleResponse(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Failed to encode success message to JSON.']));
        }

        return new GuzzleResponse(202, ['Content-Type' => 'application/json'], $json);
    }

    private function terminateProcess(string $runId): void
    {
        $process = $this->runningProcesses[$runId] ?? null;
        if (!$process instanceof Process || !$process->isRunning()) {
            return;
        }

        try {
            $process->terminate();
        } catch (Exception $exception) {
            $this->output->writeln(sprintf('<error>Failed to terminate process for run %s: %s</error>', $runId, $exception->getMessage()));
            return;
        }

        // Broadcast a stopped message immediately so the UI can react quickly
        $jsonBroadcast = json_encode(['type' => 'stopped', 'runId' => $runId]);
        if ($jsonBroadcast !== false) {
            $this->statusHandler->broadcast($jsonBroadcast);
        }

        // If the process doesn't exit within a short timeout, force-kill it (SIGKILL)
        try {
            $timeoutSeconds = 2.0;
            Loop::get()->addTimer($timeoutSeconds, function () use ($runId, $process) {
                // force kill
                $sigkill = defined('SIGKILL') ? SIGKILL : 9;
                try {
                    $process->terminate($sigkill);
                    $jsonBroadcast = json_encode(['type' => 'stopped', 'runId' => $runId, 'forced' => true]);
                    if ($jsonBroadcast !== false) {
                        $this->statusHandler->broadcast($jsonBroadcast);
                    }
                } catch (Exception $exception) {
                    $this->output->writeln(sprintf('<error>Failed to force-kill process for run %s: %s</error>', $runId, $exception->getMessage()));
                }
            });
        } catch (Exception $exception) {
            // If scheduling the timer fails for any reason, just log it and continue
            $this->output->writeln(sprintf('<error>Failed to schedule force-kill timer for run %s: %s</error>', $runId, $exception->getMessage()));
        }
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
        if (property_exists($from, 'WebSocket') && $from->WebSocket !== null) {
            $this->httpServer->onMessage($from, $msg);
        }
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
     * This method is no longer used for parsing JUnit XML.
     * The summary is now received via the 'execution.ended' realtime event.
     * This method is kept as a placeholder for potential future notification logic
     * that might need to be triggered based on the final outcome.
     *
     * @param int $exitCode The exit code of the PHPUnit process.
     * @param array<string, mixed>|null $summary The summary data from the 'execution.ended' event.
     * @param string $runId The ID of the test run.
     */
    private function notify(int $exitCode, ?array $summary, string $runId): void
    {
        $notificationTitle = 'PHPUnit Hub Test Results';

        if ($exitCode === 0) {
            $notificationMessage = 'All tests passed successfully!';
        } else {
            $failures = $summary['numberOfFailures'] ?? 0;
            $errors = $summary['numberOfErrors'] ?? 0;

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
