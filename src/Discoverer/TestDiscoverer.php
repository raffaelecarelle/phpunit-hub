<?php

namespace PHPUnitGUI\Discoverer;

use ReflectionClass;
use ReflectionMethod;
use SimpleXMLElement;
use Symfony\Component\Finder\Finder;

class TestDiscoverer
{
    private string $projectRoot;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = $projectRoot;
    }

    public function discover(): array
    {
        $configFile = $this->findConfigFile();
        if (!$configFile) {
            return [];
        }

        $testDirectories = $this->parseConfig($configFile);

        if (empty($testDirectories)) {
            return [];
        }

        return $this->findTestsInDirectories($testDirectories);
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

    private function parseConfig(string $configFile): array
    {
        $xml = new SimpleXMLElement(file_get_contents($configFile));
        $directories = [];
        foreach ($xml->xpath('//testsuite/directory') as $dir) {
            $directories[] = (string) $dir;
        }
        return $directories;
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

        return null;
    }
}
