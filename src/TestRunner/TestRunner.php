<?php

namespace PhpUnitHub\TestRunner;

use PhpUnitHub\Util\Composer;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

use function array_map;
use function escapeshellarg;
use function escapeshellcmd;
use function implode;
use function preg_quote;
use function preg_replace;
use function strtolower;
use function trim;

class TestRunner
{
    public function __construct(private readonly LoopInterface $loop)
    {
    }

    /**
     * Runs PHPUnit tests using a real-time extension.
     *
     * @param string[] $filters An array of filters to apply to the tests (e.g., method names).
     * @param string[] $groups An array of test groups to run.
     * @param string[] $suites An array of test suites to run.
     * @param array<string, bool> $options An associative array of boolean PHPUnit CLI options (e.g., ['--stop-on-failure' => true]).
     * @return Process The ReactPHP child process.
     */
    public function run(array $filters = [], array $groups = [], array $suites = [], array $options = []): Process
    {
        $phpunitPath = Composer::getComposerBinDir() . DIRECTORY_SEPARATOR . 'phpunit';

        // Check for phpunit.xml first, then fallback to phpunit.xml.dist (PHPUnit's default behavior)
        $phpunitXmlPath = getcwd() . '/phpunit.xml';
        if (!file_exists($phpunitXmlPath)) {
            $phpunitXmlPath = getcwd() . '/phpunit.xml.dist';
        }

        // We use `exec` to replace the shell process with the phpunit process.
        // This ensures that when we call `terminate()` on the ReactPHP Process object,
        // the signal is sent directly to `phpunit` rather than the intermediate shell,
        // preventing orphaned processes.
        $command = 'exec ' . escapeshellcmd($phpunitPath)
            . ' --configuration ' . escapeshellarg($phpunitXmlPath);

        // Always enable colors
        $command .= ' --colors=always';

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
        foreach ($groups as $group) {
            $command .= ' --group ' . escapeshellarg($group);
        }

        // Add boolean command-line options
        foreach ($options as $option => $isEnabled) {
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

        $process = new Process($command);
        $process->start($this->loop);

        return $process;
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
