<?php

namespace PhpUnitHub\Tests\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PhpUnitHub\Util\PhpUnitCommandExecutor;

#[CoversClass(PhpUnitCommandExecutor::class)]
class PhpUnitCommandExecutorTest extends TestCase
{
    private PhpUnitCommandExecutor $phpUnitCommandExecutor;

    protected function setUp(): void
    {
        $this->phpUnitCommandExecutor = new PhpUnitCommandExecutor();
    }

    /**
     * Test that execute method successfully runs a valid shell command
     * and returns its output.
     */
    public function testExecuteReturnsOutputOfValidCommand(): void
    {
        $command = 'echo "Hello World"';
        $expectedOutput = "Hello World\n";

        $this->assertSame($expectedOutput, $this->phpUnitCommandExecutor->execute($command));
    }

    /**
     * Test that execute method returns null for an invalid shell command.
     */
    public function testExecuteReturnsNullForInvalidCommand(): void
    {
        $command = 'invalid_command_that_does_not_exist';

        $this->assertNull($this->phpUnitCommandExecutor->execute($command));
    }

    /**
     * Test that execute method runs an empty command and returns null.
     */
    public function testExecuteReturnsNullForEmptyCommand(): void
    {
        $command = '';

        $this->assertNull($this->phpUnitCommandExecutor->execute($command));
    }
}
