<?php

namespace PhpUnitHub\Tests\Discoverer;

use ReflectionClass;
use PHPUnit\Framework\TestCase;
use PhpUnitHub\Discoverer\TestDiscoverer;
use Symfony\Component\Filesystem\Filesystem;

class TestDiscovererTest extends TestCase
{
    private string $projectRoot;

    private Filesystem $filesystem;

    /** @var array<callable> */
    private array $autoloaders = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->projectRoot = sys_get_temp_dir() . '/phpunit-gui-test-' . uniqid();
        $this->filesystem->mkdir($this->projectRoot);

        // Register a temporary autoloader for the test classes created in the temporary project root
        $callable = function ($class) {
            $filePath = null;
            if (str_starts_with($class, 'MyTests\\')) {
                $relativeClassPath = str_replace('MyTests\\', '', $class);
                $filePath = $this->projectRoot . '/tests/' . str_replace('\\', '/', $relativeClassPath) . '.php';
            } elseif (str_starts_with($class, 'App\\Tests\\')) {
                $relativeClassPath = str_replace('App\\Tests\\', '', $class);
                $filePath = $this->projectRoot . '/tests/' . str_replace('\\', '/', $relativeClassPath) . '.php';
            }

            if ($filePath && file_exists($filePath)) {
                require_once $filePath;
            }
        };

        spl_autoload_register($callable);
        $this->autoloaders[] = $callable;
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectRoot);
        foreach ($this->autoloaders as $autoloader) {
            spl_autoload_unregister($autoloader);
        }

        parent::tearDown();
    }

    private function createConfigFile(string $filename, string $content): void
    {
        $this->filesystem->dumpFile($this->projectRoot . '/' . $filename, $content);
    }

    private function getPrivateProperty(object $object, string $propertyName): mixed
    {
        $reflectionClass = new ReflectionClass($object);
        $reflectionProperty = $reflectionClass->getProperty($propertyName);
        return $reflectionProperty->getValue($object);
    }

    public function testConstructorFindsDistConfigFile(): void
    {
        $this->createConfigFile('phpunit.xml.dist', '<phpunit/>');
        $testDiscoverer = new TestDiscoverer($this->projectRoot);
        $this->assertStringEndsWith('phpunit.xml.dist', $this->getPrivateProperty($testDiscoverer, 'configFile'));
    }

    public function testConstructorFindsMainConfigFile(): void
    {
        $this->createConfigFile('phpunit.xml', '<phpunit/>');
        $testDiscoverer = new TestDiscoverer($this->projectRoot);
        $this->assertStringEndsWith('phpunit.xml', $this->getPrivateProperty($testDiscoverer, 'configFile'));
    }

    public function testConstructorPrefersDistOverMainConfigFile(): void
    {
        $this->createConfigFile('phpunit.xml.dist', '<phpunit/>');
        $this->createConfigFile('phpunit.xml', '<phpunit/>');
        $testDiscoverer = new TestDiscoverer($this->projectRoot);
        $this->assertStringEndsWith('phpunit.xml.dist', $this->getPrivateProperty($testDiscoverer, 'configFile'));
    }

    public function testConstructorFindsNoConfigFile(): void
    {
        $testDiscoverer = new TestDiscoverer($this->projectRoot);
        $this->assertNull($this->getPrivateProperty($testDiscoverer, 'configFile'));
    }

    public function testDiscoverWhenNoConfigFile(): void
    {
        $testDiscoverer = new TestDiscoverer($this->projectRoot);
        $result = $testDiscoverer->discover();
        $this->assertEquals(['suites' => [], 'availableSuites' => [], 'availableGroups' => []], $result);
    }

    public function testDiscoverWithInvalidConfigFile(): void
    {
        $this->createConfigFile('phpunit.xml', 'invalid xml');
        $testDiscoverer = new TestDiscoverer($this->projectRoot);
        $result = $testDiscoverer->discover();
        $this->assertEquals(['suites' => [], 'availableSuites' => [], 'availableGroups' => []], $result);
    }
}
