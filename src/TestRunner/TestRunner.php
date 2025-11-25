<?php

namespace PHPUnitGUI\TestRunner;

use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

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
        $phpunitPath = realpath(__DIR__ . '/../../vendor/bin/phpunit');

        if ($phpunitPath === false) {
            // Fallback if realpath fails or vendor/bin/phpunit is not found in the expected location
            // This might happen if the project structure is different or in a phar distribution
            $phpunitPath = 'vendor/bin/phpunit';
        }

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
}
