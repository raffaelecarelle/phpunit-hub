<?php

namespace PhpUnitHub\Discoverer;

use Exception;
use PhpUnitHub\Util\Composer;
use ReflectionClass;
use ReflectionException;

use function file_exists;
use function is_file;
use function preg_replace;
use function substr;
use function trim;

class TestDiscoverer
{
    private readonly ?string $configFile;

    private readonly string $phpunitPath;

    public function __construct(private readonly string $projectRoot)
    {
        $this->configFile = $this->findConfigFile();
        $this->phpunitPath = Composer::getComposerBinDir($projectRoot) . DIRECTORY_SEPARATOR . 'phpunit';
    }

    /**
     * @return array{
     *     suites: array<array{
     *         id: string,
     *         name: string,
     *         namespace: string,
     *         methods: array<array{
     *             id: string,
     *             name: string
     *         }>
     *     }>,
     *     availableSuites: string[],
     *     availableGroups: string[]
     * }
     */
    public function discover(): array
    {
        if (!is_file($this->configFile) || !is_file($this->phpunitPath)) {
            return ['suites' => [], 'availableSuites' => [], 'availableGroups' => []];
        }

        try {
            $availableSuites = $this->discoverSuites();
            $availableGroups = $this->discoverGroups();
            $foundTests = $this->discoverTests();
        } catch (Exception) {
            return ['suites' => [], 'availableSuites' => [], 'availableGroups' => []];
        }

        return [
            'suites' => $foundTests,
            'availableSuites' => $availableSuites,
            'availableGroups' => $availableGroups,
        ];
    }

    /**
     * @return string[]
     */
    private function executePhpUnitCommand(string $command): array
    {
        $fullCommand = 'cd ' . escapeshellarg($this->projectRoot) . ' && ' . escapeshellcmd($this->phpunitPath) . ' ' . $command;
        $output = shell_exec($fullCommand);
        if ($output === null) {
            return [];
        }

        return explode("\n", trim($output));
    }

    /**
     * @return string[]
     */
    public function discoverSuites(): array
    {
        $lines = $this->executePhpUnitCommand('--list-suites');
        $suites = [];
        $foundList = false;
        foreach ($lines as $line) {
            if (str_contains($line, 'Available test suite')) {
                $foundList = true;
                continue;
            }

            if ($foundList && str_starts_with($line, ' - ')) {
                $key = preg_replace('/\s*\(\d+\s*tests?\)$/', '', trim(substr($line, 3)));
                $suites[$key] = trim(substr($line, 3));
            }
        }

        return $suites;
    }

    /**
     * @return string[]
     */
    public function discoverGroups(): array
    {
        $lines = $this->executePhpUnitCommand('--list-groups');
        $groups = [];
        $foundList = false;
        foreach ($lines as $line) {
            if (str_contains($line, 'Available test group')) {
                $foundList = true;
                continue;
            }

            if ($foundList && str_starts_with($line, ' - ')) {
                $key = preg_replace('/\s*\(\d+\s*tests?\)$/', '', trim(substr($line, 3)));
                $groups[$key] = trim(substr($line, 3));
            }
        }

        return $groups;
    }

    /**
     * @return array<array{
     *      id: string,
     *      name: string,
     *      namespace: string,
     *      methods: array<array{
     *          id: string,
     *          name: string
     *      }>
     *  }>
     */
    private function discoverTests(): array
    {
        $lines = $this->executePhpUnitCommand('--list-tests');
        $tests = [];
        $foundList = false;
        foreach ($lines as $line) {
            if (str_contains($line, 'Available test')) {
                $foundList = true;
                continue;
            }

            if ($foundList && str_starts_with($line, ' - ')) {
                $tests[] = trim(substr($line, 3));
            }
        }

        $suites = [];
        foreach ($tests as $test) {
            if (!str_contains($test, '::')) {
                continue;
            }

            [$className, $methodName] = explode('::', $test, 2);

            if (!isset($suites[$className])) {
                $parts = explode('\\', $className);
                $shortName = end($parts);
                $namespace = implode('\\', array_slice($parts, 0, -1));
                $suites[$className] = [
                    'id' => $className,
                    'name' => $shortName,
                    'namespace' => $namespace,
                    'methods' => [],
                ];
            }

            $suites[$className]['methods'][] = [
                'id' => $test,
                'name' => $methodName,
            ];
        }

        return array_values($suites);
    }

    private function findConfigFile(): ?string
    {
        $dist = $this->projectRoot . '/phpunit.xml.dist';
        if (file_exists($dist)) {
            return $dist;
        }

        $main = $this->projectRoot . '/phpunit.xml';
        if (file_exists($main)) {
            return $main;
        }

        return null;
    }
}
