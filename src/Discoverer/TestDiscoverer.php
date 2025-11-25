<?php

namespace PHPUnitGUI\Discoverer;

use ReflectionClass;
use ReflectionMethod;
use SimpleXMLElement;
use Symfony\Component\Finder\Finder;

class TestDiscoverer
{
    private string $projectRoot;
    private ?string $configFile;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = $projectRoot;
        $this->configFile = $this->findConfigFile();
    }

    public function discover(): array
    {
        if (!$this->configFile) {
            return ['suites' => [], 'availableSuites' => []];
        }

        $testDirectories = $this->parseTestDirectories($this->configFile);
        $foundTests = empty($testDirectories) ? [] : $this->findTestsInDirectories($testDirectories);
        $availableSuites = $this->discoverSuites();

        return [
            'suites' => $foundTests,
            'availableSuites' => $availableSuites,
        ];
    }

    public function discoverSuites(): array
    {
        if (!$this->configFile) {
            return [];
        }

        $xml = new SimpleXMLElement(file_get_contents($this->configFile));
        $suites = [];
        foreach ($xml->xpath('//testsuites/testsuite') as $suiteNode) {
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

    private function parseTestDirectories(string $configFile): array
    {
        $xml = new SimpleXMLElement(file_get_contents($configFile));
        $directories = [];
        // We look for all directories inside any testsuite to discover individual tests
        foreach ($xml->xpath('//testsuite/directory') as $dir) {
            $fullPath = $this->projectRoot . '/' . (string) $dir;
            if (is_dir($fullPath)) {
                $directories[] = $fullPath;
            }
        }
        return array_unique($directories);
    }

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
                if (!$reflection->isInstantiable() || !$reflection->isSubclassOf('PHPUnit\\Framework\\TestCase')) {
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

                if (!empty($methods)) {
                    $suites[] = [
                        'id' => $className,
                        'name' => $reflection->getShortName(),
                        'namespace' => $reflection->getNamespaceName(),
                        'methods' => $methods,
                    ];
                }
            } catch (\ReflectionException $e) {
                // Could not autoload the class, skip it.
                continue;
            }
        }
        return $suites;
    }

    private function getClassNameFromFile(string $filePath): ?string
    {
        $tokens = token_get_all(file_get_contents($filePath));
        $namespace = '';
        $class = '';

        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                $namespace = ''; // Reset for each namespace statement
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if ($tokens[$j] === ';') {
                        break;
                    }
                    if (is_array($tokens[$j])) {
                        $namespace .= $tokens[$j][1];
                    }
                }
            }

            if ($tokens[$i][0] === T_CLASS) {
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if ($tokens[$j] === '{') {
                        $class = $tokens[$i + 2][1];
                        break 2;
                    }
                }
            }
        }

        if ($namespace && $class) {
            return trim($namespace) . '\\' . $class;
        }
        
        if ($class) {
            return $class;
        }

        return null;
    }
}
