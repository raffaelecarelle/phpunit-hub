<?php

namespace PHPUnitGUI\TestRunner;

use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

class TestRunner
{
    public function __construct(private readonly LoopInterface $loop)
    {
    }

    public function run(string $junitLogfile, array $filters = []): Process
    {
        $phpunitPath = realpath('vendor/bin/phpunit') ?: 'vendor/bin/phpunit';

        $command = escapeshellcmd($phpunitPath)
            . ' --log-junit ' . escapeshellarg($junitLogfile);

        if (!empty($filters)) {
            // The --filter option expects a regular expression.
            // To match the filter strings literally, we must escape any special regex characters.
            // preg_quote is the correct tool for this, as it will handle `\` and other special chars.
            $escapedFilters = array_map(function ($filter) {
                // The second argument adds the regex delimiter `/` to the list of characters to be escaped,
                // although it's not strictly necessary for the default delimiter used by PHPUnit.
                // It's good practice for robustness.
                return preg_quote($filter, '/');
            }, $filters);

            // Join multiple filters with the regex OR operator.
            $filterPattern = implode('|', $escapedFilters);

            $command .= ' --filter ' . escapeshellarg($filterPattern);
        }

        $process = new Process($command);
        $process->start($this->loop);

        return $process;
    }
}
