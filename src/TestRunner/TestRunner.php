<?php

namespace PhpUnitHub\TestRunner;

use PhpUnitHub\Util\Composer;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

use function array_map;
use function escapeshellarg;
use function escapeshellcmd;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function preg_quote;
use function preg_replace;
use function str_replace;
use function strtolower;
use function sys_get_temp_dir;
use function trim;
use function uniqid;

class TestRunner
{
    public function __construct(private readonly LoopInterface $loop)
    {
    }

    /**
     * Runs PHPUnit tests using a real-time extension.
     *
     * @param string $realtimeOutputFile The path to the file where the RealtimeTestExtension will write events.
     * @param string[] $filters An array of filters to apply to the tests (e.g., method names).
     * @param string[] $groups An array of test groups to run.
     * @param string[] $suites An array of test suites to run.
     * @param array<string, bool> $options An associative array of boolean PHPUnit CLI options (e.g., ['--stop-on-failure' => true]).
     * @return Process The ReactPHP child process.
     */
    public function run(string $realtimeOutputFile, array $filters = [], array $groups = [], array $suites = [], array $options = []): Process
    {
        $phpunitPath = Composer::getComposerBinDir() . DIRECTORY_SEPARATOR . 'phpunit';
        $phpunitXmlPath = getcwd() . '/phpunit.xml.dist';

        // Create a temporary phpunit.xml for this run
        $tempPhpunitXmlPath = sys_get_temp_dir() . '/phpunit-hub-temp-' . uniqid() . '.xml';
        $phpunitXmlContent = file_get_contents($phpunitXmlPath);
        $modifiedPhpunitXmlContent = str_replace('__REALTIME_OUTPUT_FILE__', $realtimeOutputFile, $phpunitXmlContent);
        file_put_contents($tempPhpunitXmlPath, $modifiedPhpunitXmlContent);

        $command = escapeshellcmd($phpunitPath)
            . ' --configuration ' . escapeshellarg($tempPhpunitXmlPath); // Use the temporary config file

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

        // Clean up the temporary phpunit.xml file after the process exits
        $process->on('exit', function () use ($tempPhpunitXmlPath) {
            if (file_exists($tempPhpunitXmlPath)) {
                unlink($tempPhpunitXmlPath);
            }
        });

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
