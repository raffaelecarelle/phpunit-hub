<?php

namespace PhpUnitHub\Tests\TestRunner;

use React\ChildProcess\Process;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PhpUnitHub\TestRunner\TestRunner;
use React\EventLoop\LoopInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

#[CoversClass(TestRunner::class)]
class TestRunnerTest extends TestCase
{
    private LoopInterface $loop;

    private string $tempDir;

    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loop = $this->createMock(LoopInterface::class);
        $this->tempDir = sys_get_temp_dir() . '/phpunit-gui-test-runner-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);

        // Create a fake vendor/bin directory
        $binDir = $this->tempDir . '/vendor/bin';
        mkdir($binDir, 0777, true);
        file_put_contents($binDir . '/phpunit', '#!/usr/bin/env php');
        file_put_contents($binDir . '/paratest', '#!/usr/bin/env php');
        chmod($binDir . '/phpunit', 0755);
        chmod($binDir . '/paratest', 0755);


        $this->originalCwd = getcwd();
        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        if (is_dir($this->tempDir)) {
            $this->deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->deleteDirectory("$dir/$file") : unlink("$dir/$file");
        }

        rmdir($dir);
    }

    public function testRunReturnsProcessInstance(): void
    {
        $testRunner = new TestRunner($this->loop, $this->tempDir);
        $process = $testRunner->run(['filters' => [], 'coverage' => false]);
        $this->assertInstanceOf(Process::class, $process);
    }

    public function testRunBuildsCorrectCommandForParatest(): void
    {
        $testRunner = new TestRunner($this->loop, $this->tempDir);
        $testRunner->run(['filters' => [], 'coverage' => false, 'parallel' => true]);

        $command = $testRunner->getLastCommand();

        $this->assertStringContainsString('bin/paratest', $command);
        $this->assertStringNotContainsString('bin/phpunit', $command);
    }

    /**
     * @throws ReflectionException
     */
    public function testRunSetsTcpPortEnvironmentVariable(): void
    {
        $testRunner = new TestRunner($this->loop, $this->tempDir);
        $process = $testRunner->run(['filters' => [], 'coverage' => false]);

        $reflectionClass = new ReflectionClass(Process::class);
        $envProperty = $reflectionClass->getProperty('env');
        $env = $envProperty->getValue($process);

        $this->assertArrayHasKey('PHPUNIT_GUI_TCP_PORT', $env);
        $this->assertIsNumeric($env['PHPUNIT_GUI_TCP_PORT']);
    }

    /**
     * @throws ReflectionException
     */
    public function testRunBuildsCorrectCommandWithCoverage(): void
    {
        $phpunitXmlContent = <<<XML_WRAP
            <?xml version="1.0" encoding="UTF-8"?>
            <phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                     xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd"
                     bootstrap="vendor/autoload.php"
                     colors="true">
                <source>
                    <include>
                        <directory suffix=".php">src</directory>
                    </include>
                    <exclude>
                        <directory suffix=".php">src/Exclude</directory>
                    </exclude>
                </source>
                <coverage>
                    <report>
                        <clover outputFile="clover.xml"/>
                    </report>
                </coverage>
            </phpunit>
            XML_WRAP;
        file_put_contents($this->tempDir . '/phpunit.xml', $phpunitXmlContent);

        $testRunner = new TestRunner($this->loop, $this->tempDir);
        $testRunner->run(['filters' => [], 'coverage' => true]);

        $command = $testRunner->getLastCommand();

        $this->assertStringContainsString('--coverage-clover', $command);
        $this->assertStringContainsString('clover.xml', $command);
        $this->assertStringContainsString('--coverage-filter ' . escapeshellarg('src'), $command);
        $this->assertStringContainsString('--coverage-exclude ' . escapeshellarg('src/Exclude'), $command);
    }

    public function testRunBuildsCorrectCommandWithFilters(): void
    {
        $testRunner = new TestRunner($this->loop, $this->tempDir);
        $testRunner->run(['filters' => ['MyTest', 'AnotherTest'], 'coverage' => false]);

        $command = $testRunner->getLastCommand();

        $this->assertStringContainsString('--filter', $command);
        $this->assertStringContainsString('MyTest|AnotherTest', $command);
    }

    public function testRunBuildsCorrectCommandWithSuites(): void
    {
        $testRunner = new TestRunner($this->loop, $this->tempDir);
        $testRunner->run(['suites' => ['MySuite', 'AnotherSuite'], 'filters' => [], 'coverage' => false]);

        $command = $testRunner->getLastCommand();

        $this->assertStringContainsString('--testsuite', $command);
        $this->assertStringContainsString('MySuite', $command);
        $this->assertStringContainsString('AnotherSuite', $command);
    }

    public function testRunBuildsCorrectCommandWithGroups(): void
    {
        $testRunner = new TestRunner($this->loop, $this->tempDir);
        $testRunner->run(['groups' => ['MyGroup', 'AnotherGroup'], 'filters' => [], 'coverage' => false]);

        $command = $testRunner->getLastCommand();

        $this->assertStringContainsString('--group', $command);
        $this->assertStringContainsString('MyGroup', $command);
        $this->assertStringContainsString('AnotherGroup', $command);
    }

    public function testRunBuildsCorrectCommandWithOptions(): void
    {
        $testRunner = new TestRunner($this->loop, $this->tempDir);
        $testRunner->run(['options' => ['stopOnFailure' => true], 'filters' => [], 'coverage' => false]);

        $command = $testRunner->getLastCommand();

        $this->assertStringContainsString('--stop-on-failure', $command);
    }

    public function testCamelToKebab(): void
    {
        $testRunner = new TestRunner($this->loop, $this->tempDir);
        $reflectionMethod = new ReflectionMethod(TestRunner::class, 'camelToKebab');
        $result = $reflectionMethod->invoke($testRunner, 'stopOnFailure');
        $this->assertEquals('stop-on-failure', $result);
    }

    public function testRunBuildsCorrectCommandWithCoverageAndNoSource(): void
    {
        $phpunitXmlContent = <<<XML_WRAP
            <?xml version="1.0" encoding="UTF-8"?>
            <phpunit>
                <coverage>
                    <report>
                        <clover outputFile="clover.xml"/>
                    </report>
                </coverage>
            </phpunit>
            XML_WRAP;
        file_put_contents($this->tempDir . '/phpunit.xml', $phpunitXmlContent);

        $testRunner = new TestRunner($this->loop, $this->tempDir);
        $testRunner->run(['filters' => [], 'coverage' => true]);

        $command = $testRunner->getLastCommand();

        $this->assertStringContainsString('--coverage-clover', $command);
        $this->assertStringNotContainsString('--coverage-filter', $command);
    }
}
