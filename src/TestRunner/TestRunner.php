<?php

namespace PHPUnitGUI\TestRunner;

use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

class TestRunner
{
    public function __construct(private readonly LoopInterface $loop)
    {
    }

    public function run(string $junitLogfile, array $filters = [], string $group = '', array $suites = [], array $options = []): Process
    {
        $phpunitPath = realpath('vendor/bin/phpunit') ?: 'vendor/bin/phpunit';

        $command = escapeshellcmd($phpunitPath)
            . ' --log-junit ' . escapeshellarg($junitLogfile);

        // Add test suite filters if provided
        if (!empty($suites)) {
            foreach ($suites as $suite) {
                $command .= ' --testsuite ' . escapeshellarg($suite);
            }
        }

        // Add name/group filters if provided
        if (!empty($filters)) {
            $escapedFilters = array_map(fn($filter) => preg_quote($filter, '/'), $filters);
            $filterPattern = implode('|', $escapedFilters);
            $command .= ' --filter ' . escapeshellarg($filterPattern);
        }

        // Add --group filter if provided
        if (!empty($group)) {
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
