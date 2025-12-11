<?php

namespace PhpUnitHub\TestRunner;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PhpUnitHub\Util\Composer;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;
use React\Stream\ThroughStream;

use function array_map;
use function escapeshellarg;
use function escapeshellcmd;
use function file_exists;
use function implode;
use function preg_quote;
use function preg_replace;
use function strtolower;
use function trim;
use function stream_socket_server;
use function stream_set_blocking;
use function stream_socket_get_name;
use function stream_socket_accept;
use function stream_get_contents;
use function fclose;
use function getenv;
use function array_merge;

/**
 * The TestRunner class is responsible for constructing and executing PHPUnit or ParaTest commands.
 * It builds the command based on user-provided context (filters, groups, etc.),
 * manages the child process, and sets up a communication channel for real-time event reporting.
 */
class TestRunner
{
    /**
     * Stores the last command string that was executed. Useful for debugging.
     */
    private ?string $lastCommand = null;

    public function __construct(
        private readonly LoopInterface $loop,
        private readonly string $projectRoot
    ) {
    }

    /**
     * Executes a PHPUnit/ParaTest run with the specified context.
     *
     * @param array{
     *     suites?: array<string>,
     *     filters: array<string>,
     *     groups?: array<string>,
     *     options?: array<string, bool>,
     *     coverage: bool,
     *     parallel?: bool
     * } $context The context for the test run, including filters, options, and coverage settings.
     * @return Process The ReactPHP Process object for the running test command.
     */
    public function run(array $context): Process
    {
        // Determine whether to use 'phpunit' for sequential runs or 'paratest' for parallel runs.
        $binary = ($context['parallel'] ?? false) ? 'paratest' : 'phpunit';
        $executablePath = Composer::getComposerBinDir($this->projectRoot) . DIRECTORY_SEPARATOR . $binary;

        // Check for phpunit.xml first, then fall back to phpunit.xml.dist, mirroring PHPUnit's default behavior.
        $phpunitXmlPath = $this->projectRoot . '/phpunit.xml';
        if (!file_exists($phpunitXmlPath)) {
            $phpunitXmlPath = $this->projectRoot . '/phpunit.xml.dist';
        }

        // We use `exec` to replace the shell process with the phpunit process.
        // This is a crucial detail to ensure that when we call `terminate()` on the ReactPHP Process object,
        // the signal is sent directly to the test runner (`phpunit` or `paratest`) rather than the
        // intermediate shell. This prevents orphaned processes from being left behind after termination.
        // We also increase the memory limit, as code coverage generation can be very memory-intensive.
        $command = 'exec php -d memory_limit=-1 ' . escapeshellcmd($executablePath)
            . ' --configuration ' . escapeshellarg($phpunitXmlPath);

        // Always enable colors for a consistent output experience.
        $command .= ' --colors=always';

        // Add test suite filters if provided.
        foreach ($context['suites'] ?? [] as $suite) {
            $command .= ' --testsuite ' . escapeshellarg($suite);
        }

        // Add name filters if provided. This allows running specific tests by name.
        if (!empty($context['filters'])) {
            $escapedFilters = array_map(fn (string $filter) => preg_quote($filter, '/'), $context['filters']);
            $filterPattern = implode('|', $escapedFilters);
            $command .= ' --filter ' . escapeshellarg($filterPattern);
        }

        // Add group filters if provided.
        foreach ($context['groups'] ?? [] as $group) {
            $command .= ' --group ' . escapeshellarg($group);
        }

        // Add any other boolean command-line options from the context.
        foreach ($context['options'] ?? [] as $option => $isEnabled) {
            if ($option === 'displayRisky') {
                continue;
            }

            if ($isEnabled) {
                // Convert the camelCase option name from the frontend to the kebab-case CLI flag.
                $optionFlag = '--' . $this->camelToKebab($option);
                $command .= ' ' . escapeshellarg($optionFlag);
            }
        }

        // If code coverage is requested, parse the phpunit.xml to add the correct options.
        if ($context['coverage']) {
            $this->addCoverageOptions($command, $phpunitXmlPath);
        }

        $this->lastCommand = $command;

        // --- TCP Server for Real-time Event Streaming ---
        // When using ParaTest, workers buffer their output. To work around this, our PhpUnitHubExtension
        // connects to a TCP server to send events in real-time.

        // 1. Create a TCP server socket on a random free port.
        $tcpServer = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        stream_set_blocking($tcpServer, false);

        // 2. Retrieve the dynamically assigned port.
        $address = stream_socket_get_name($tcpServer, false);
        $port = (int) substr(strrchr($address, ':'), 1);

        // 3. Pass the port to the child process via an environment variable.
        $env = array_merge(getenv(), ['PHPUNIT_GUI_TCP_PORT' => (string) $port]);

        $process = new Process($command, $this->projectRoot, $env);
        $process->start($this->loop);

        // 4. Create a composite stream to merge the process's actual STDERR with the TCP event stream.
        $throughStream = new ThroughStream();

        // Forward any actual STDERR output from the process to our composite stream.
        $process->stderr->on('data', fn ($data) => $throughStream->write($data));

        // 5. Listen for incoming connections on the TCP server.
        $this->loop->addReadStream($tcpServer, function ($server) use ($throughStream) {
            $conn = @stream_socket_accept($server);
            if ($conn) {
                stream_set_blocking($conn, false);
                $this->loop->addReadStream($conn, function ($conn) use ($throughStream) {
                    $data = stream_get_contents($conn);
                    if ($data === '' || $data === false) {
                        // Connection closed by the worker
                        $this->loop->removeReadStream($conn);
                        fclose($conn);
                        return;
                    }
                    $throughStream->write($data);
                });
            }
        });

        // 6. Replace the original stderr stream on the process object with our composite stream.
        $process->stderr = $throughStream;

        // 7. Clean up the server socket when the main process exits.
        $process->on('exit', function () use ($tcpServer) {
            $this->loop->removeReadStream($tcpServer);
            fclose($tcpServer);
        });

        return $process;
    }

    /**
     * Returns the last command string that was executed.
     */
    public function getLastCommand(): ?string
    {
        return $this->lastCommand;
    }

    /**
     * Parses the phpunit.xml file to add the appropriate code coverage options to the command.
     * This ensures that coverage is generated according to the project's configuration,
     * respecting the specified output files and source file filters.
     */
    private function addCoverageOptions(string &$command, string $phpunitXmlPath): void
    {
        $domDocument = new DOMDocument();
        // Suppress errors for malformed XML, as we can't guarantee the file is valid.
        @$domDocument->load($phpunitXmlPath);
        $domxPath = new DOMXPath($domDocument);

        // Find the configured Clover report file.
        $cloverReport = $domxPath->query('//coverage/report/clover')->item(0);
        $cloverFile = $this->projectRoot . '/clover.xml'; // Default value
        if ($cloverReport instanceof DOMElement && $cloverReport->hasAttribute('outputFile')) {
            $cloverFile = $cloverReport->getAttribute('outputFile');
        }

        $command .= ' --coverage-clover ' . escapeshellarg($cloverFile);

        // Find source inclusion/exclusion paths to pass as filters.
        $sourceNode = $domxPath->query('//source')->item(0);
        if (!$sourceNode) {
            return;
        }

        $includeNodes = $domxPath->query('include/directory', $sourceNode);
        foreach ($includeNodes as $includeNode) {
            $command .= ' --coverage-filter ' . escapeshellarg((string) $includeNode->nodeValue);
        }

        $excludeNodes = $domxPath->query('exclude/directory', $sourceNode);
        foreach ($excludeNodes as $excludeNode) {
            $command .= ' --coverage-exclude ' . escapeshellarg((string) $excludeNode->nodeValue);
        }
    }

    /**
     * A utility function to convert a camelCase string to kebab-case.
     * e.g., "displayRisky" becomes "display-risky".
     */
    private function camelToKebab(string $input): string
    {
        $s = preg_replace('/[_\s]+/', '-', $input);
        $s = preg_replace('/([a-z\d])([A-Z])/', '$1-$2', (string) $s);
        $s = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1-$2', (string) $s);
        $s = strtolower((string) preg_replace('/-+/', '-', (string) $s));

        return trim($s, '-');
    }
}
