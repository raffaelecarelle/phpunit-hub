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

        $xml = new SimpleXMLElement(file_get_contents($this->configFile));
        $suites = [];
        $suiteNodes = $xml->xpath('//testsuites/testsuite');

        if ($suiteNodes === false) {
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
        $xml = new SimpleXMLElement(file_get_contents($configFile));
        $directories = [];
        $dirNodes = $xml->xpath('//testsuite/directory');

        if ($dirNodes === false) {
            return [];
        }

        foreach ($dirNodes as $dirNode) {
            $fullPath = $this->projectRoot . '/' . $dirNode;
            if (is_dir($fullPath)) {
                $directories[] = $fullPath;
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

        $suites = [];
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
                    $suites[] = [
                        'id' => $className,
                        'name' => $reflection->getShortName(),
                        'namespace' => $reflection->getNamespaceName(),
                        'methods' => $methods,
                    ];
                }
            } catch (ReflectionException) {
                // Could not autoload the class, skip it.
                continue;
            }
        }

        return $suites;
    }

    private function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
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
