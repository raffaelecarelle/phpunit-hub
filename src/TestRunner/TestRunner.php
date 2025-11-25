<?php

namespace PHPUnitGUI\TestRunner;

use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

use function array_map;
use function escapeshellarg;
use function escapeshellcmd;
use function file_get_contents;
use function getcwd;
use function implode;
use function is_array;
use function is_readable;
use function is_string;
use function json_decode;
use function preg_match;
use function preg_quote;
use function rtrim;
use function var_dump;

class TestRunner
{
    public function __construct(private readonly LoopInterface $loop)
    {
    }

    /**
     * Runs PHPUnit tests.
     *
     * @param string $junitLogfile The path to the JUnit XML log file.
     * @param string[] $filters An array of filters to apply to the tests (e.g., method names).
     * @param string $group The test group to run.
     * @param string[] $suites An array of test suites to run.
     * @param array<string, bool> $options An associative array of boolean PHPUnit CLI options (e.g., ['--stop-on-failure' => true]).
     * @return Process The ReactPHP child process.
     */
    public function run(string $junitLogfile, array $filters = [], string $group = '', array $suites = [], array $options = []): Process
    {
        $phpunitPath = $this->getComposerBinDir() . DIRECTORY_SEPARATOR . 'phpunit';

        $command = escapeshellcmd($phpunitPath)
            . ' --log-junit ' . escapeshellarg($junitLogfile);

        // Add test suite filters if provided
        foreach ($suites as $suite) {
            $command .= ' --testsuite ' . escapeshellarg($suite);
        }

        // Add name/group filters if provided
        if ($filters !== []) {
            $escapedFilters = array_map(fn (string $filter) => preg_quote($filter, '/'), $filters);
            $filterPattern = implode('|', $escapedFilters);
            $command .= ' --filter ' . escapeshellarg($filterPattern);
        }

        // Add --group filter if provided
        if ($group !== '') {
            $command .= ' --group ' . escapeshellarg($group);
        }

        // Add boolean command-line options
        foreach ($options as $option => $isEnabled) {
            if ($isEnabled) {
                // Assuming the option name from the frontend matches the PHPUnit CLI flag
                $command .= ' ' . escapeshellarg($option);
            }
        }

        $process = new Process($command);
        $process->start($this->loop);

        return $process;
    }

    public function getComposerBinDir(string $projectDir = null): string
    {
        $projectDir ??= getcwd();
        $composerFile = rtrim($projectDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'composer.json';

        if (!is_readable($composerFile)) {
            return $projectDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        }

        $data = json_decode(file_get_contents($composerFile), true);
        if (!is_array($data)) {
            return $projectDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        }

        $binDir = $data['config']['bin-dir'] ?? null;
        if (!$binDir || !is_string($binDir)) {
            return $projectDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        }

        // Resolve relative paths to absolute
        if (!preg_match('#^(?:/|[A-Za-z]:\\\\|\\\\)#', $binDir)) {
            $binDir = $projectDir . DIRECTORY_SEPARATOR . $binDir;
        }

        return rtrim($binDir, DIRECTORY_SEPARATOR);
    }
}
