<?php

namespace PHPUnitGUI\Tests\TestRunner;

use PHPUnit\Framework\TestCase;
use PHPUnitGUI\TestRunner\TestRunner;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

class TestRunnerTest extends TestCase
{
    private LoopInterface $loop;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loop = $this->createMock(LoopInterface::class);
        $this->tempDir = sys_get_temp_dir() . '/phpunit-gui-test-runner-' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob("$this->tempDir/*.*"));
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function testRunReturnsProcessInstance(): void
    {
        $runner = new TestRunner($this->loop);
        $junitLogfile = $this->tempDir . '/logfile.xml';

        // The TestRunner directly instantiates React\ChildProcess\Process.
        // Without refactoring TestRunner to allow injecting a mockable Process
        // or a factory for it, we cannot reliably verify the command string
        // or mock the Process's behavior in a unit test.
        // This test only verifies that an instance of Process is returned.
        // For more comprehensive testing, TestRunner would need to be refactored.

        $process = $runner->run($junitLogfile);
        $this->assertInstanceOf(Process::class, $process);
    }

    // Due to the direct instantiation of `React\ChildProcess\Process` within the `run` method,
    // it is not possible to write isolated unit tests that verify the exact command string
    // passed to `Process` or mock its behavior without modifying the `TestRunner` class itself.
    //
    // To properly test the command string generation and Process interaction,
    // `TestRunner` would need to be refactored to:
    // 1. Accept a `ProcessFactory` or `Process` instance via its constructor.
    // 2. Or, have a protected method for creating `Process` instances that can be
    //    overridden in a test subclass.
    //
    // Without such refactoring, any tests attempting to verify the command string
    // would either be integration tests (actually running `phpunit` or a dummy script)
    // or rely on fragile reflection hacks that are not recommended for maintainable tests.
    //
    // Therefore, more detailed tests for command string construction are omitted
    // under the current constraints.
}
