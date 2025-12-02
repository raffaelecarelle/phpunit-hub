<?php

namespace PhpUnitHub\Tests\Discoverer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PhpUnitHub\Discoverer\TestDiscoverer;
use PhpUnitHub\Util\Composer;
use PhpUnitHub\Util\PhpUnitCommandExecutor;
use ReflectionClass;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(TestDiscoverer::class)]
class TestDiscovererTest extends TestCase
{
    private string $projectRoot;

    private Filesystem $filesystem;

    private PhpUnitCommandExecutor&MockObject $phpUnitCommandExecutor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->projectRoot = sys_get_temp_dir() . '/phpunit-gui-test-' . uniqid('', true);
        $this->filesystem->mkdir($this->projectRoot);
        $this->phpUnitCommandExecutor = $this->createMock(PhpUnitCommandExecutor::class);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectRoot);
        parent::tearDown();
    }

    private function createConfigFile(string $filename, string $content): void
    {
        $this->filesystem->dumpFile($this->projectRoot . '/' . $filename, $content);
    }

    private function getPrivateProperty(object $object, string $propertyName): mixed
    {
        $reflectionClass = new ReflectionClass($object);
        return $reflectionClass->getProperty($propertyName)->getValue($object);
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
        $this->assertEquals(['suites' => [], 'availableSuites' => [], 'availableGroups' => [], 'coverageDriver' => false], $result);
    }

    public function testDiscoverWithInvalidConfigFile(): void
    {
        $this->createConfigFile('phpunit.xml', 'invalid xml');
        $testDiscoverer = new TestDiscoverer($this->projectRoot);
        $result = $testDiscoverer->discover();
        $this->assertEquals(['suites' => [], 'availableSuites' => [], 'availableGroups' => [], 'coverageDriver' => false], $result);
    }

    public function testDiscoverSuites(): void
    {
        $this->createConfigFile('phpunit.xml', '<phpunit/>');
        $this->filesystem->dumpFile(Composer::getComposerBinDir($this->projectRoot) . '/phpunit', '#!/usr/bin/env php');
        $this->phpUnitCommandExecutor->method('execute')->willReturn("Available test suites:\n - My Test Suite");
        $testDiscoverer = new TestDiscoverer($this->projectRoot, $this->phpUnitCommandExecutor);
        $result = $testDiscoverer->discoverSuites();
        $this->assertEquals(['My Test Suite' => 'My Test Suite'], $result);
    }

    public function testDiscoverGroups(): void
    {
        $this->createConfigFile('phpunit.xml', '<phpunit/>');
        $this->filesystem->dumpFile(Composer::getComposerBinDir($this->projectRoot) . '/phpunit', '#!/usr/bin/env php');
        $this->phpUnitCommandExecutor->method('execute')->willReturn("Available test groups:\n - MyGroup");
        $testDiscoverer = new TestDiscoverer($this->projectRoot, $this->phpUnitCommandExecutor);
        $result = $testDiscoverer->discoverGroups();
        $this->assertEquals(['MyGroup' => 'MyGroup'], $result);
    }

    public function testDiscoverTests(): void
    {
        $this->createConfigFile('phpunit.xml', '<phpunit/>');
        $this->filesystem->dumpFile(Composer::getComposerBinDir($this->projectRoot) . '/phpunit', '#!/usr/bin/env php');
        $this->phpUnitCommandExecutor->method('execute')->willReturn("Available tests:\n - MyTests\MyFirstTest::testOne");
        $testDiscoverer = new TestDiscoverer($this->projectRoot, $this->phpUnitCommandExecutor);
        $result = $testDiscoverer->discover();
        $this->assertNotEmpty($result['suites']);
    }
}
