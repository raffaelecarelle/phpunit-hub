<?php

namespace PhpUnitHub\TestRunner;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PhpUnitHub\Util\Composer;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

use function array_map;
use function escapeshellarg;
use function escapeshellcmd;
use function file_exists;
use function implode;
use function preg_quote;
use function preg_replace;
use function sprintf;
use function strtolower;
use function trim;

class TestRunner
{
    private ?string $lastCommand = null;

    public function __construct(
        private readonly LoopInterface $loop,
        private readonly string $projectRoot
    ) {
    }


    /**
     * @param array{
     *     suites?: array<string>,
     *     filters: array<string>,
     *     groups?: array<string>,
     *     options?: array<string, bool>,
     *     coverage: bool
     * } $context
     */
    public function run(array $context, string $runId): Process
    {
        $phpunitPath = Composer::getComposerBinDir($this->projectRoot) . DIRECTORY_SEPARATOR . 'phpunit';

        // Check for phpunit.xml first, then fallback to phpunit.xml.dist (PHPUnit's default behavior)
        $phpunitXmlPath = $this->projectRoot . '/phpunit.xml';
        if (!file_exists($phpunitXmlPath)) {
            $phpunitXmlPath = $this->projectRoot . '/phpunit.xml.dist';
        }

        // We use `exec` to replace the shell process with the phpunit process.
        // This ensures that when we call `terminate()` on the ReactPHP Process object,
        // the signal is sent directly to `phpunit` rather than the intermediate shell,
        // preventing orphaned processes.
        // We also increase the memory limit, as code coverage can be memory-intensive.
        $command = 'exec php -d memory_limit=-1 ' . escapeshellcmd($phpunitPath)
            . ' --configuration ' . escapeshellarg($phpunitXmlPath);

        // Always enable colors
        $command .= ' --colors=always';

        // Add test suite filters if provided
        foreach ($context['suites'] ?? [] as $suite) {
            $command .= ' --testsuite ' . escapeshellarg($suite);
        }

        // Add name/group filters if provided
        if ($context['filters'] !== []) {
            $escapedFilters = array_map(fn (string $filter) => preg_quote($filter, '/'), $context['filters']);
            $filterPattern = implode('|', $escapedFilters);
            $command .= ' --filter ' . escapeshellarg($filterPattern);
        }

        // Add --group filter if provided
        foreach ($context['groups'] ?? [] as $group) {
            $command .= ' --group ' . escapeshellarg($group);
        }

        // Add boolean command-line options
        foreach ($context['options'] ?? [] as $option => $isEnabled) {
            if ($isEnabled) {
                // Skip the generic handling for 'colors' as we've already handled it.
                if ($option === 'colors') {
                    continue;
                }

                $option = '--'.$this->camelToKebab($option);
                // Assuming the option name from the frontend matches the PHPUnit CLI flag
                $command .= ' ' . escapeshellarg($option);
            }
        }

        if ($context['coverage']) {
            $this->addCoverageOptions($command, $phpunitXmlPath, $runId);
        }

        $this->lastCommand = $command;

        $process = new Process($command, $this->projectRoot);
        $process->start($this->loop);

        return $process;
    }

    public function getLastCommand(): ?string
    {
        return $this->lastCommand;
    }

    private function addCoverageOptions(string &$command, string $phpunitXmlPath, string $runId): void
    {
        $domDocument = new DOMDocument();
        @$domDocument->load($phpunitXmlPath);
        $domxPath = new DOMXPath($domDocument);

        $cloverReport = $domxPath->query('//coverage/report/clover')->item(0);

        $cloverFile =  $this->projectRoot . sprintf('/clover-%s.xml', $runId);

        if ($cloverReport instanceof DOMElement) {
            $cloverFile = $cloverReport->getAttribute('outputFile');
        }

        $command .= ' --coverage-clover ' . escapeshellarg((string) $cloverFile);

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
            $command .= ' --coverage-filter ' . escapeshellarg((string) $excludeNode->nodeValue) . ' --path-coverage';
        }
    }

    private function camelToKebab(string $input): string
    {
        $s = preg_replace('/[_\s]+/', '-', $input);
        $s = preg_replace('/([a-z\d])([A-Z])/', '$1-$2', (string) $s);
        $s = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1-$2', (string) $s);
        $s = strtolower((string) preg_replace('/-+/', '-', (string) $s));

        return trim($s, '-');
    }
}
