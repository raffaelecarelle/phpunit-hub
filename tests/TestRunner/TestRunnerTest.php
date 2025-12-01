<?php

namespace PhpUnitHub\Tests\TestRunner;

use PHPUnit\Framework\TestCase;
use PhpUnitHub\TestRunner\TestRunner;
use React\EventLoop\LoopInterface;
use ReflectionClass;
use ReflectionException;

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
        $testRunner = new TestRunner($this->loop);
        /** @var string[] $filters */
        $filters = [];
        $testRunner->run(['filters' => $filters, 'coverage' => false], 'test-run-id');
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

        $testRunner = new TestRunner($this->loop);
        /** @var string[] $filters */
        $filters = [];
        $process = $testRunner->run(['filters' => $filters, 'coverage' => true], 'test-run-id');

        $reflectionClass = new ReflectionClass($process);
        $reflectionProperty = $reflectionClass->getProperty('command');

        $command = $reflectionProperty->getValue($process);

        $this->assertStringContainsString('--coverage-clover', $command);
        $this->assertStringContainsString('clover.xml', $command);
        $this->assertStringContainsString('--coverage-filter ' . escapeshellarg('src'), $command);
        $this->assertStringContainsString('--coverage-filter ' . escapeshellarg('src/Exclude') . ' --path-coverage', $command);
    }
}
