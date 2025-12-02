<?php

namespace PhpUnitHub\Tests\TestRunner;

use React\ChildProcess\Process;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PhpUnitHub\TestRunner\TestRunner;
use React\EventLoop\LoopInterface;
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
        mkdir($this->tempDir);

        $this->originalCwd = getcwd();
        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }

            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    public function testRunReturnsProcessInstance(): void
    {
        $testRunner = new TestRunner($this->loop, $this->tempDir);
        $process = $testRunner->run(['filters' => [], 'coverage' => false], 'test-run-id');
        $this->assertInstanceOf(Process::class, $process);
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
        $testRunner->run(['filters' => [], 'coverage' => true], 'test-run-id');

        $command = $testRunner->getLastCommand();

        $this->assertStringContainsString('--coverage-clover', $command);
        $this->assertStringContainsString('clover.xml', $command);
        $this->assertStringContainsString('--coverage-filter ' . escapeshellarg('src'), $command);
        $this->assertStringContainsString('--coverage-exclude ' . escapeshellarg('src/Exclude'), $command);
    }

    public function testRunBuildsCorrectCommandWithFilters(): void
    {
        $testRunner = new TestRunner($this->loop, $this->tempDir);
        $testRunner->run(['filters' => ['MyTest', 'AnotherTest'], 'coverage' => false], 'test-run-id');

        $command = $testRunner->getLastCommand();

        $this->assertStringContainsString('--filter', $command);
        $this->assertStringContainsString('MyTest|AnotherTest', $command);
    }

    public function testRunBuildsCorrectCommandWithSuites(): void
    {
        $testRunner = new TestRunner($this->loop, $this->tempDir);
        $testRunner->run(['suites' => ['MySuite', 'AnotherSuite'], 'filters' => [], 'coverage' => false], 'test-run-id');

        $command = $testRunner->getLastCommand();

        $this->assertStringContainsString('--testsuite', $command);
        $this->assertStringContainsString('MySuite', $command);
        $this->assertStringContainsString('AnotherSuite', $command);
    }

    public function testRunBuildsCorrectCommandWithGroups(): void
    {
        $testRunner = new TestRunner($this->loop, $this->tempDir);
        $testRunner->run(['groups' => ['MyGroup', 'AnotherGroup'], 'filters' => [], 'coverage' => false], 'test-run-id');

        $command = $testRunner->getLastCommand();

        $this->assertStringContainsString('--group', $command);
        $this->assertStringContainsString('MyGroup', $command);
        $this->assertStringContainsString('AnotherGroup', $command);
    }

    public function testRunBuildsCorrectCommandWithOptions(): void
    {
        $testRunner = new TestRunner($this->loop, $this->tempDir);
        $testRunner->run(['options' => ['stopOnFailure' => true], 'filters' => [], 'coverage' => false], 'test-run-id');

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
        $testRunner->run(['filters' => [], 'coverage' => true], 'test-run-id');

        $command = $testRunner->getLastCommand();

        $this->assertStringContainsString('--coverage-clover', $command);
        $this->assertStringNotContainsString('--coverage-filter', $command);
    }
}
