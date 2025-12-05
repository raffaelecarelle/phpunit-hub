<?php

namespace PhpUnitHub\Command;

use Exception;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PhpUnitHub\Coverage\Coverage;
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
use function hash_file;
use function implode;
use function is_file;
use function json_decode;
use function json_encode;
use function pathinfo;
use function property_exists;
use function realpath;
use function sprintf;
use function str_replace;
use function str_starts_with;

// Aggiunto per chiarezza, anche se probabilmente già presente

class Router implements RouterInterface
{
    private readonly string $publicPath;

    /** @var array<string, Process> */
    private array $runningProcesses = [];

    /** @var array<string, array<string, mixed>> */ // Mappa runId a lastFilters, lastSuites, lastGroups, lastOptions
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

    private ?Coverage $coverage = null;

    public function __construct(
        private readonly HttpServerInterface $httpServer,
        private readonly OutputInterface     $output,
        private readonly StatusHandler       $statusHandler,
        private readonly TestRunner          $testRunner,
        private readonly TestDiscoverer      $testDiscoverer,
        private readonly string              $projectRoot,
        private readonly string              $host = '127.0.0.1',
        private readonly string              $port = '8080'
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
            $jsonResponse = json_encode($tests, JSON_THROW_ON_ERROR);
            $response = new GuzzleResponse(200, ['Content-Type' => 'application/json'], $jsonResponse);
        } elseif (($path === '/api/run' || $path === '/api/run-failed') && $method === 'POST') {
            $groups = [];
            /** @var string[] $suites */
            $suites = [];
            /** @var array<string, bool> $options */
            $options = [];
            $isRerun = false;
            $coverage = false;

            if ($path === '/api/run') {
                $body = $request?->getBody()->getContents();
                $payload = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR) ?? [];
                $filters = $payload['filters'] ?? [];
                $groups = $payload['groups'] ?? [];
                $suites = $payload['suites'] ?? [];
                $options = $payload['options'] ?? [];
                $coverage = $payload['coverage'] ?? false;
                $contextId = $payload['contextId'] ?? null; // Get contextId from payload
                $this->lastFilters = $filters;
                $this->lastSuites = $suites;
                $this->lastGroups = $groups;
                $this->lastOptions = $options;
            } else { // /api/run-failed
                $isRerun = true;
                if ($this->failedTests === []) {
                    $jsonResponse = json_encode(['error' => 'No failed tests to run.'], JSON_THROW_ON_ERROR);
                    $response = new GuzzleResponse(400, ['Content-Type' => 'application/json'], $jsonResponse);

                    $this->sendResponse($connection, $response);
                    return;
                }

                $filters = $this->failedTests;
                // For run-failed, we might not have a specific contextId, or it could be 'failed'
                $body = $request?->getBody()->getContents();
                $payload = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR) ?? [];
                $contextId = $payload['contextId'] ?? 'failed';
            }

            $runId = $this->runTests($filters, $suites, $groups, $options, $isRerun, $contextId, $coverage);
            $jsonResponse = json_encode(['message' => 'Test run started.', 'runId' => $runId], JSON_THROW_ON_ERROR);
            $response = new GuzzleResponse(202, ['Content-Type' => 'application/json'], $jsonResponse);
        } elseif ($path === '/api/stop' && $method === 'POST') {
            // Handle stop requests for all running tests
            $response = $this->stopAllTests();
        } elseif (str_starts_with((string) $path, '/api/stop-single-test/') && $method === 'POST') {
            $parts = explode('/', (string) $path);
            $runId = end($parts);
            $response = $this->stopSingleTest($runId);
        } elseif (str_starts_with((string) $path, '/api/coverage/') && $method === 'GET') {
            $parts = explode('/', (string) $path);
            $runId = end($parts);
            $response = $this->getCoverage($runId);
        } elseif ($path === '/api/file-coverage' && $method === 'GET') {
            $queryParams = [];
            parse_str((string) $request?->getUri()->getQuery(), $queryParams);
            $filePath = $queryParams['path'] ?? null;
            $runId = $queryParams['runId'] ?? null;
            $response = $this->getFileCoverage($runId, $filePath);
        } elseif ($path === '/api/file-content' && $method === 'GET') {
            $queryParams = [];
            parse_str((string) $request?->getUri()->getQuery(), $queryParams);
            $filePath = $queryParams['path'] ?? null;
            $response = $this->getFileContent($filePath);
        } else {
            $filePath = $this->publicPath . $path;
            if ($path === '/') {
                $filePath = $this->publicPath . '/index.html';
            }

            if (file_exists($filePath) && is_file($filePath) && str_starts_with(realpath($filePath), $this->publicPath)) {
                $content = file_get_contents($filePath);

                if ($path === '/' || $path === '/index.html') {
                    $manifestPath = $this->publicPath . '/build/.vite/manifest.json';
                    $viteScriptTag = '<script type="module" src="/js/main.js"></script>'; // Fallback per lo sviluppo
                    $viteCssTags = ''; // Conterrà i tag CSS generati da Vite

                    // Tag CSS di fallback (per lo sviluppo o se Vite non genera CSS)
                    $cssPath = $this->publicPath . '/css/styles.css';
                    $cssVersion = file_exists($cssPath) ? hash_file('md5', $cssPath) : 'dev';
                    $fallbackCssTag = '<link rel="stylesheet" href="css/styles.css?v=' . $cssVersion . '">';

                    if (file_exists($manifestPath)) {
                        $manifest = json_decode(file_get_contents($manifestPath), true);
                        $mainEntry = $manifest['public/js/main.js'] ?? null;

                        if ($mainEntry) {
                            $viteScriptTag = '<script type="module" src="/build/' . $mainEntry['file'] . '"></script>';

                            if (!empty($mainEntry['css'])) {
                                foreach ($mainEntry['css'] as $cssFile) {
                                    $viteCssTags .= '<link rel="stylesheet" href="/build/' . $cssFile . '">';
                                }
                            }
                        }
                    }

                    // Sostituisci il vecchio tag script con quello di Vite o il fallback
                    $content = str_replace('<script type="module" src="/js/main.js"></script>', $viteScriptTag, $content);

                    // Rimuovi il vecchio link al manifest di Vite (non è per il browser)
                    // Sostituisci il vecchio link CSS. Se Vite ha generato CSS, usa quello. Altrimenti, usa il fallback.
                    // Sostituisci i placeholder di host/port
                    $content = str_replace(array('<link rel="manifest" href="/.vite/manifest.json" />', '<link rel="stylesheet" href="css/styles.css?v={{css_version}}">', '{{ws_host}}', '{{ws_port}}'), array('', !empty($viteCssTags) ? $viteCssTags : $fallbackCssTag, $this->host, $this->port), $content);
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
    public function runTests(array $filters, array $suites = [], array $groups = [], array $options = [], bool $isRerun = false, ?string $contextId = null, bool $coverage = false): string
    {
        $runId = Uuid::uuid4()->toString();

        $this->runContexts[$runId] = [
            'filters' => $filters,
            'suites' => $suites,
            'groups' => $groups,
            'options' => $options,
            'contextId' => $contextId, // Store contextId
            'coverage' => $coverage,
        ];

        $this->output->writeln(sprintf('Starting test run #%s with filters: ', $runId) . implode(', ', $filters));

        $jsonBroadcast = json_encode(['type' => 'start', 'runId' => $runId, 'contextId' => $contextId], JSON_THROW_ON_ERROR); // Include contextId
        $this->statusHandler->broadcast($jsonBroadcast);

        $process = $this->testRunner->run($this->runContexts[$runId], $runId);

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

                if (json_validate($line) === false) {
                    continue;
                }

                $this->output->writeln(sprintf('[DEBUG] Processing line: %s', $line));

                // Each line is a JSON object from our RealtimeTestExtension
                $decodedLine = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if ($decodedLine === null) {
                    $this->output->writeln(sprintf('<error>Failed to decode JSON from realtime output: %s</error>', $line));
                    continue;
                }

                $this->output->writeln(sprintf('[DEBUG] Decoded event: %s', $decodedLine['event'] ?? 'unknown'));

                // Store the summary if it's the execution.ended event
                if ($decodedLine['event'] === 'execution.ended') {
                    $this->lastRunSummaries[$runId] = $decodedLine['data']['summary'];
                }

                $jsonBroadcast = json_encode(['type' => 'realtime', 'runId' => $runId, 'data' => $line], JSON_THROW_ON_ERROR);
                $this->output->writeln('[DEBUG] Broadcasting realtime event');
                $this->statusHandler->broadcast($jsonBroadcast);
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

                    if (json_validate($line) === false) {
                        continue;
                    }

                    $decodedLine = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    if ($decodedLine === null) {
                        $this->output->writeln(sprintf('<error>Failed to decode JSON from final realtime output: %s</error>', $line));
                        continue;
                    }

                    if ($decodedLine['event'] === 'execution.ended') {
                        $this->lastRunSummaries[$runId] = $decodedLine['data']['summary'];
                    }

                    $jsonBroadcast = json_encode(['type' => 'realtime', 'runId' => $runId, 'data' => $line], JSON_THROW_ON_ERROR);
                    $this->statusHandler->broadcast($jsonBroadcast);
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
            ], JSON_THROW_ON_ERROR);

            $this->statusHandler->broadcast($jsonBroadcast);

            if ($exitCode) {
                $this->notify($exitCode, $summary, $runId);
            }

            // Clear running process/runId state
            unset($this->runningProcesses[$runId]);
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
            $json = json_encode(['error' => 'No test run in progress.'], JSON_THROW_ON_ERROR);
            return new GuzzleResponse(400, ['Content-Type' => 'application/json'], $json);
        }

        foreach (array_keys($this->runningProcesses) as $runId) {
            $this->terminateProcess($runId);
        }

        $json = json_encode(['message' => 'Stop requested for all running tests.'], JSON_THROW_ON_ERROR);
        return new GuzzleResponse(202, ['Content-Type' => 'application/json'], $json);
    }

    /**
     * Attempt to stop a specific running test process.
     */
    private function stopSingleTest(string $runId): GuzzleResponse
    {
        if (!isset($this->runningProcesses[$runId])) {
            $json = json_encode(['error' => sprintf('No test run found with ID %s.', $runId)], JSON_THROW_ON_ERROR);
            return new GuzzleResponse(404, ['Content-Type' => 'application/json'], $json);
        }

        $this->terminateProcess($runId);

        $json = json_encode(['message' => sprintf('Stop requested for test run %s.', $runId)], JSON_THROW_ON_ERROR);
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
        $jsonBroadcast = json_encode(['type' => 'stopped', 'runId' => $runId], JSON_THROW_ON_ERROR);
        $this->statusHandler->broadcast($jsonBroadcast);

        // If the process doesn't exit within a short timeout, force-kill it (SIGKILL)
        try {
            $timeoutSeconds = 2.0;
            Loop::get()->addTimer($timeoutSeconds, function () use ($runId, $process) {
                // force kill
                $sigkill = defined('SIGKILL') ? SIGKILL : 9;
                try {
                    $process->terminate($sigkill);
                    $jsonBroadcast = json_encode(['type' => 'stopped', 'runId' => $runId, 'forced' => true], JSON_THROW_ON_ERROR);
                    $this->statusHandler->broadcast($jsonBroadcast);
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

    private function getCoverage(string $runId): GuzzleResponse
    {
        if (!$this->coverage instanceof Coverage) {
            $coveragePath = $this->projectRoot . sprintf('/clover-%s.xml', $runId);
            $this->coverage = new Coverage($this->projectRoot, $coveragePath);
        }

        $data = $this->coverage->parse();

        return new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function getFileCoverage(?string $runId, ?string $filePath): GuzzleResponse
    {
        if ($filePath === null) {
            return new GuzzleResponse(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'File path not provided.'], JSON_THROW_ON_ERROR));
        }

        if ($runId === null) {
            return new GuzzleResponse(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'Run ID not provided.'], JSON_THROW_ON_ERROR));
        }

        $coveragePath = $this->projectRoot . sprintf('/clover-%s.xml', $runId);

        $coverage = new Coverage($this->projectRoot, $coveragePath);

        // Sanitize file path
        $realPath = realpath($this->projectRoot . '/' . $filePath);
        if ($realPath === false || !str_starts_with($realPath, $this->projectRoot)) {
            return new GuzzleResponse(403, ['Content-Type' => 'application/json'], json_encode(['error' => 'Access denied.'], JSON_THROW_ON_ERROR));
        }

        $data = $coverage->parseFile($filePath);

        return new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function getFileContent(?string $filePath): GuzzleResponse
    {
        if ($filePath === null) {
            return new GuzzleResponse(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'File path not provided.'], JSON_THROW_ON_ERROR));
        }

        $realPath = realpath($filePath);
        if ($realPath !== false) { // The path was resolved
            if (!str_starts_with($realPath, $this->projectRoot)) {
                // Resolved path is outside project root
                return new GuzzleResponse(403, ['Content-Type' => 'application/json'], json_encode(['error' => 'Access denied.'], JSON_THROW_ON_ERROR));
            }
        } else { // realpath failed
            // This could be because the file does not exist, or a component of the path is not accessible.
            // We need to check for path traversal on the given path to be safe.
            if (str_contains($filePath, '..')) {
                return new GuzzleResponse(403, ['Content-Type' => 'application/json'], json_encode(['error' => 'Access denied.'], JSON_THROW_ON_ERROR));
            }

            // Check if path starts with project root
            if (!str_starts_with($filePath, $this->projectRoot)) {
                return new GuzzleResponse(403, ['Content-Type' => 'application/json'], json_encode(['error' => 'Access denied.'], JSON_THROW_ON_ERROR));
            }

            // At this point, we assume it's a non-existent file inside the project.
            return new GuzzleResponse(404, ['Content-Type' => 'application/json'], json_encode(['error' => 'File not found.'], JSON_THROW_ON_ERROR));
        }

        // If we are here, realpath was successful and inside project root.
        $content = file_get_contents($realPath);

        return new GuzzleResponse(200, ['Content-Type' => 'text/plain'], $content);
    }
}