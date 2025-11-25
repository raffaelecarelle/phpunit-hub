<?php

namespace PHPUnitGUI\Discoverer;

use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use SimpleXMLElement;
use Symfony\Component\Finder\Finder;

class TestDiscoverer
{
    private readonly ?string $configFile;

    public function __construct(private readonly string $projectRoot)
    {
        $this->configFile = $this->findConfigFile();
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
     *     availableSuites: string[]
     * }
     */
    public function discover(): array
    {
        if (!$this->configFile) {
            return ['suites' => [], 'availableSuites' => []];
        }

        try {
            $testDirectories = $this->parseTestDirectories($this->configFile);
            $foundTests = $testDirectories === [] ? [] : $this->findTestsInDirectories($testDirectories);
            $availableSuites = $this->discoverSuites();
        } catch (Exception) {
            return ['suites' => [], 'availableSuites' => []];
        }

        return [
            'suites' => $foundTests,
            'availableSuites' => $availableSuites,
        ];
    }

    /**
     * @return string[]
     * @throws Exception
     */
    public function discoverSuites(): array
    {
        if (!$this->configFile) {
            return [];
        }

        $fileContents = file_get_contents($this->configFile);
        if ($fileContents === false) {
            throw new Exception(sprintf('Could not read config file: %s', $this->configFile));
        }

        $xml = new SimpleXMLElement($fileContents);
        $suites = [];
        $suiteNodes = $xml->xpath('//testsuites/testsuite');

        if (!is_array($suiteNodes)) { // Changed from === false
            return [];
        }

        foreach ($suiteNodes as $suiteNode) {
            $suites[] = (string) $suiteNode['name'];
        }

        return $suites;
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

    /**
     * @return string[]
     * @throws Exception
     */
    private function parseTestDirectories(string $configFile): array
    {
        $fileContents = file_get_contents($configFile);
        if ($fileContents === false) {
            throw new Exception(sprintf('Could not read config file: %s', $configFile));
        }

        $xml = new SimpleXMLElement($fileContents);
        $directories = [];
        $dirNodes = $xml->xpath('//testsuite/directory');

        if (!is_array($dirNodes)) { // Changed from === false
            return [];
        }

        foreach ($dirNodes as $dirNode) {
            $fullPath = $this->projectRoot . '/' . $dirNode;
            $normalizedPath = realpath($fullPath); // Normalize the path
            if ($normalizedPath !== false && is_dir($normalizedPath)) {
                $directories[] = $normalizedPath;
            }
        }

        return array_unique($directories);
    }

    /**
     * @param string[] $directories
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
    private function findTestsInDirectories(array $directories): array
    {
        $finder = new Finder();
        $finder->files()->in($directories)->name('*Test.php');

        $uniqueSuites = []; // Use an associative array to ensure uniqueness by suite ID
        foreach ($finder as $file) {
            $className = $this->getClassNameFromFile($file->getRealPath());
            if (!$className) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($className);
                if (!$reflection->isInstantiable()) {
                    continue;
                }

                if (!$reflection->isSubclassOf(TestCase::class)) {
                    continue;
                }

                $methods = [];
                foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    if (str_starts_with($method->getName(), 'test')) {
                        $methods[] = [
                            'id' => $className . '::' . $method->getName(),
                            'name' => $method->getName(),
                        ];
                    }
                }

                if ($methods !== []) {
                    $suiteId = $className; // The class name is a unique identifier for the suite
                    // Add the suite only if it hasn't been added yet
                    if (!isset($uniqueSuites[$suiteId])) {
                        $uniqueSuites[$suiteId] = [
                            'id' => $className,
                            'name' => $reflection->getShortName(),
                            'namespace' => $reflection->getNamespaceName(),
                            'methods' => $methods,
                        ];
                    }
                }
            } catch (ReflectionException) {
                // Could not autoload the class, skip it.
                continue;
            }
        }

        return array_values($uniqueSuites); // Convert back to a numerically indexed array
    }

    private function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        if (preg_match('/^namespace\s+([^;]+);/m', $content, $namespaceMatches) &&
            preg_match('/^class\s+([^{\s]+)/m', $content, $classMatches)) {
            return trim($namespaceMatches[1]) . '\\' . $classMatches[1];
        }

        if (preg_match('/^class\s+([^{\s]+)/m', $content, $classMatches)) {
            return $classMatches[1];
        }

        return null;
    }
}
