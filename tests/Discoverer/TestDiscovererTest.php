<?php

namespace PHPUnitGUI\Tests\Discoverer;

use PHPUnit\Framework\TestCase;
use PHPUnitGUI\Discoverer\TestDiscoverer;
use Symfony\Component\Filesystem\Filesystem;

class TestDiscovererTest extends TestCase
{
    private string $projectRoot;
    private Filesystem $filesystem;
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

    private function createTestFile(string $path, string $content): void
    {
        $this->filesystem->dumpFile($this->projectRoot . '/' . $path, $content);
    }

    private function getPrivateProperty(object $object, string $propertyName): mixed
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    public function testConstructorFindsDistConfigFile(): void
    {
        $this->createConfigFile('phpunit.xml.dist', '<phpunit/>');
        $discoverer = new TestDiscoverer($this->projectRoot);
        $this->assertStringEndsWith('phpunit.xml.dist', $this->getPrivateProperty($discoverer, 'configFile'));
    }

    public function testConstructorFindsMainConfigFile(): void
    {
        $this->createConfigFile('phpunit.xml', '<phpunit/>');
        $discoverer = new TestDiscoverer($this->projectRoot);
        $this->assertStringEndsWith('phpunit.xml', $this->getPrivateProperty($discoverer, 'configFile'));
    }

    public function testConstructorPrefersDistOverMainConfigFile(): void
    {
        $this->createConfigFile('phpunit.xml.dist', '<phpunit/>');
        $this->createConfigFile('phpunit.xml', '<phpunit/>');
        $discoverer = new TestDiscoverer($this->projectRoot);
        $this->assertStringEndsWith('phpunit.xml.dist', $this->getPrivateProperty($discoverer, 'configFile'));
    }

    public function testConstructorFindsNoConfigFile(): void
    {
        $discoverer = new TestDiscoverer($this->projectRoot);
        $this->assertNull($this->getPrivateProperty($discoverer, 'configFile'));
    }

    public function testDiscoverSuitesWithValidConfigFile(): void
    {
        $configContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <testsuites>
        <testsuite name="MyTestSuite">
            <directory>./tests</directory>
        </testsuite>
        <testsuite name="AnotherTestSuite">
            <directory>./another_tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
XML;
        $this->createConfigFile('phpunit.xml', $configContent);
        $discoverer = new TestDiscoverer($this->projectRoot);
        $suites = $discoverer->discoverSuites();
        $this->assertEquals(['MyTestSuite', 'AnotherTestSuite'], $suites);
    }

    public function testDiscoverSuitesWithNoTestSuites(): void
    {
        $configContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
</phpunit>
XML;
        $this->createConfigFile('phpunit.xml', $configContent);
        $discoverer = new TestDiscoverer($this->projectRoot);
        $suites = $discoverer->discoverSuites();
        $this->assertEquals([], $suites);
    }

    public function testDiscoverSuitesWithNoConfigFile(): void
    {
        $discoverer = new TestDiscoverer($this->projectRoot);
        $suites = $discoverer->discoverSuites();
        $this->assertEquals([], $suites);
    }

    public function testDiscoverSuitesWithInvalidConfigFile(): void
    {
        $this->createConfigFile('phpunit.xml', 'invalid xml');
        $discoverer = new TestDiscoverer($this->projectRoot);
        $this->expectException(\Exception::class); // SimpleXMLElement throws on invalid XML
        $discoverer->discoverSuites();
    }

    public function testParseTestDirectoriesWithValidConfigFile(): void
    {
        $this->filesystem->mkdir($this->projectRoot . '/tests');
        $this->filesystem->mkdir($this->projectRoot . '/another_tests');
        $configContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <testsuites>
        <testsuite name="MyTestSuite">
            <directory>./tests</directory>
            <directory>./another_tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
XML;
        $this->createConfigFile('phpunit.xml', $configContent);
        $discoverer = new TestDiscoverer($this->projectRoot);

        // Access private method using reflection
        $reflection = new \ReflectionClass($discoverer);
        $method = $reflection->getMethod('parseTestDirectories');
        $method->setAccessible(true);

        $directories = $method->invoke($discoverer, $this->projectRoot . '/phpunit.xml');
        $expectedPaths = [
            $this->projectRoot . '/tests',
            $this->projectRoot . '/another_tests',
        ];
        sort($directories);
        sort($expectedPaths);
        $this->assertEquals($expectedPaths, $directories);
    }

    public function testParseTestDirectoriesWithNonExistentDirectory(): void
    {
        $configContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <testsuites>
        <testsuite name="MyTestSuite">
            <directory>./non_existent_tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
XML;
        $this->createConfigFile('phpunit.xml', $configContent);
        $discoverer = new TestDiscoverer($this->projectRoot);

        $reflection = new \ReflectionClass($discoverer);
        $method = $reflection->getMethod('parseTestDirectories');
        $method->setAccessible(true);

        $directories = $method->invoke($discoverer, $this->projectRoot . '/phpunit.xml');
        $this->assertEquals([], $directories);
    }

    public function testParseTestDirectoriesWithNoDirectoriesInConfig(): void
    {
        $configContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <testsuites>
        <testsuite name="MyTestSuite">
        </testsuite>
    </testsuites>
</phpunit>
XML;
        $this->createConfigFile('phpunit.xml', $configContent);
        $discoverer = new TestDiscoverer($this->projectRoot);

        $reflection = new \ReflectionClass($discoverer);
        $method = $reflection->getMethod('parseTestDirectories');
        $method->setAccessible(true);

        $directories = $method->invoke($discoverer, $this->projectRoot . '/phpunit.xml');
        $this->assertEquals([], $directories);
    }

    public function testGetClassNameFromFileWithNamespaceAndClass(): void
    {
        $content = <<<PHP
<?php
namespace MyNamespace\\SubNamespace;
class MyTestClass {}
PHP;
        $this->createTestFile('src/MyNamespace/SubNamespace/MyTestClass.php', $content);
        $discoverer = new TestDiscoverer($this->projectRoot);

        $reflection = new \ReflectionClass($discoverer);
        $method = $reflection->getMethod('getClassNameFromFile');
        $method->setAccessible(true);

        $className = $method->invoke($discoverer, $this->projectRoot . '/src/MyNamespace/SubNamespace/MyTestClass.php');
        $this->assertEquals('MyNamespace\\SubNamespace\\MyTestClass', $className);
    }

    public function testGetClassNameFromFileWithOnlyClass(): void
    {
        $content = <<<PHP
<?php
class AnotherTestClass {}
PHP;
        $this->createTestFile('src/AnotherTestClass.php', $content);
        $discoverer = new TestDiscoverer($this->projectRoot);

        $reflection = new \ReflectionClass($discoverer);
        $method = $reflection->getMethod('getClassNameFromFile');
        $method->setAccessible(true);

        $className = $method->invoke($discoverer, $this->projectRoot . '/src/AnotherTestClass.php');
        $this->assertEquals('AnotherTestClass', $className);
    }

    public function testGetClassNameFromFileWithNoClass(): void
    {
        $content = <<<PHP
<?php
namespace MyNamespace;
// No class defined
PHP;
        $this->createTestFile('src/NoClassFile.php', $content);
        $discoverer = new TestDiscoverer($this->projectRoot);

        $reflection = new \ReflectionClass($discoverer);
        $method = $reflection->getMethod('getClassNameFromFile');
        $method->setAccessible(true);

        $className = $method->invoke($discoverer, $this->projectRoot . '/src/NoClassFile.php');
        $this->assertNull($className);
    }

    public function testGetClassNameFromFileWithEmptyFile(): void
    {
        $this->createTestFile('src/EmptyFile.php', '');
        $discoverer = new TestDiscoverer($this->projectRoot);

        $reflection = new \ReflectionClass($discoverer);
        $method = $reflection->getMethod('getClassNameFromFile');
        $method->setAccessible(true);

        $className = $method->invoke($discoverer, $this->projectRoot . '/src/EmptyFile.php');
        $this->assertNull($className);
    }

    public function testFindTestsInDirectories(): void
    {
        $this->filesystem->mkdir($this->projectRoot . '/tests');
        $this->createTestFile('tests/ExampleTest.php', <<<PHP
<?php
namespace MyTests;
use PHPUnit\Framework\TestCase;
class ExampleTest extends TestCase
{
    public function testAddition(): void {}
    public function testSubtraction(): void {}
    public function helperMethod(): void {}
}
PHP);
        $this->createTestFile('tests/AnotherTest.php', <<<PHP
<?php
namespace MyTests;
use PHPUnit\Framework\TestCase;
class AnotherTest extends TestCase
{
    public function testSomething(): void {}
}
PHP);
        $this->createTestFile('tests/NotATestClass.php', <<<PHP
<?php
namespace MyTests;
class NotATestClass {}
PHP);
        $this->createTestFile('tests/AbstractTest.php', <<<PHP
<?php
namespace MyTests;
use PHPUnit\Framework\TestCase;
abstract class AbstractTest extends TestCase
{
    public function testAbstract(): void {}
}
PHP);

        $discoverer = new TestDiscoverer($this->projectRoot);

        $reflection = new \ReflectionClass($discoverer);
        $method = $reflection->getMethod('findTestsInDirectories');
        $method->setAccessible(true);

        $foundSuites = $method->invoke($discoverer, [$this->projectRoot . '/tests']);

        $expected = [
            [
                'id' => 'MyTests\\ExampleTest',
                'name' => 'ExampleTest',
                'namespace' => 'MyTests',
                'methods' => [
                    ['id' => 'MyTests\\ExampleTest::testAddition', 'name' => 'testAddition'],
                    ['id' => 'MyTests\\ExampleTest::testSubtraction', 'name' => 'testSubtraction'],
                ],
            ],
            [
                'id' => 'MyTests\\AnotherTest',
                'name' => 'AnotherTest',
                'namespace' => 'MyTests',
                'methods' => [
                    ['id' => 'MyTests\\AnotherTest::testSomething', 'name' => 'testSomething'],
                ],
            ],
        ];

        // Sort by 'id' to ensure consistent order for comparison
        usort($foundSuites, fn($a, $b) => $a['id'] <=> $b['id']);
        usort($expected, fn($a, $b) => $a['id'] <=> $b['id']);

        $this->assertEquals($expected, $foundSuites);
    }

    public function testDiscoverFullFlow(): void
    {
        $this->filesystem->mkdir($this->projectRoot . '/tests');
        $this->createConfigFile('phpunit.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <testsuites>
        <testsuite name="Application">
            <directory>./tests</directory>
        </testsuite>
        <testsuite name="Unit">
            <directory>./tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
XML);
        $this->createTestFile('tests/MyClassTest.php', <<<PHP
<?php
namespace App\\Tests;
use PHPUnit\Framework\TestCase;
class MyClassTest extends TestCase
{
    public function testBasicFunctionality(): void {}
}
PHP);
        $this->filesystem->mkdir($this->projectRoot . '/tests/Unit');
        $this->createTestFile('tests/Unit/AnotherUnitTest.php', <<<PHP
<?php
namespace App\\Tests\\Unit;
use PHPUnit\Framework\TestCase;
class AnotherUnitTest extends TestCase
{
    public function testUnitFeature(): void {}
    public function testAnotherUnitFeature(): void {}
}
PHP);

        $discoverer = new TestDiscoverer($this->projectRoot);
        $result = $discoverer->discover();

        $this->assertArrayHasKey('suites', $result);
        $this->assertArrayHasKey('availableSuites', $result);

        $this->assertEquals(['Application', 'Unit'], $result['availableSuites']);

        $expectedSuites = [
            [
                'id' => 'App\\Tests\\MyClassTest',
                'name' => 'MyClassTest',
                'namespace' => 'App\\Tests',
                'methods' => [
                    ['id' => 'App\\Tests\\MyClassTest::testBasicFunctionality', 'name' => 'testBasicFunctionality'],
                ],
            ],
            [
                'id' => 'App\\Tests\\Unit\\AnotherUnitTest',
                'name' => 'AnotherUnitTest',
                'namespace' => 'App\\Tests\\Unit',
                'methods' => [
                    ['id' => 'App\\Tests\\Unit\\AnotherUnitTest::testUnitFeature', 'name' => 'testUnitFeature'],
                    ['id' => 'App\\Tests\\Unit\\AnotherUnitTest::testAnotherUnitFeature', 'name' => 'testAnotherUnitFeature'],
                ],
            ],
        ];

        // Sort by 'id' to ensure consistent order for comparison
        usort($result['suites'], fn($a, $b) => $a['id'] <=> $b['id']);
        usort($expectedSuites, fn($a, $b) => $a['id'] <=> $b['id']);

        var_dump($result['suites']);
        $this->assertEquals($expectedSuites, $result['suites']);
    }

    public function testDiscoverWhenNoConfigFile(): void
    {
        $discoverer = new TestDiscoverer($this->projectRoot);
        $result = $discoverer->discover();
        $this->assertEquals(['suites' => [], 'availableSuites' => []], $result);
    }

    public function testDiscoverWhenParseTestDirectoriesThrowsException(): void
    {
        // Simulate an invalid config file that would cause parseTestDirectories to throw
        $this->createConfigFile('phpunit.xml', 'invalid xml content');
        $discoverer = new TestDiscoverer($this->projectRoot);
        $result = $discoverer->discover();
        $this->assertEquals(['suites' => [], 'availableSuites' => []], $result);
    }
}
