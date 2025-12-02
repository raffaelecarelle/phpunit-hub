<?php

namespace PhpUnitHub\Tests\WebSocket;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use ReflectionClass;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PhpUnitHub\WebSocket\StatusHandler;
use Ratchet\ConnectionInterface;
use SplObjectStorage;
use Symfony\Component\Console\Output\OutputInterface;

// Custom stub for ConnectionInterface to avoid dynamic property deprecation
class TestConnection implements ConnectionInterface
{
    public int $resourceId;

    public function send($msg): ConnectionInterface
    {
        return $this;
    }

    public function close(): void
    {
    }
}

#[CoversClass(StatusHandler::class)]
class StatusHandlerTest extends TestCase
{
    private OutputInterface&MockObject $output;

    private StatusHandler $statusHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->output = $this->createMock(OutputInterface::class);
        $this->statusHandler = new StatusHandler($this->output);
    }

    public function testOnOpenAttachesConnectionAndWritesToOutput(): void
    {
        $testConnection = new TestConnection();
        $testConnection->resourceId = 123;

        $this->output->expects($this->once())
            ->method('writeln')
            ->with('New connection! (123)', OutputInterface::VERBOSITY_VERBOSE);

        $this->statusHandler->onOpen($testConnection);

        // Use reflection to access the private connections property
        $reflectionClass = new ReflectionClass($this->statusHandler);
        $reflectionProperty = $reflectionClass->getProperty('connections');
        /** @var SplObjectStorage<ConnectionInterface, null> $connections */
        $connections = $reflectionProperty->getValue($this->statusHandler);

        $this->assertTrue($connections->offsetExists($testConnection));
    }

    public function testOnOpenWithoutOutputDoesNotWrite(): void
    {
        $statusHandler = new StatusHandler(); // No output interface
        $testConnection = new TestConnection();
        $testConnection->resourceId = 123;

        $this->output->expects($this->never())->method('writeln');

        $statusHandler->onOpen($testConnection);

        $reflectionClass = new ReflectionClass($statusHandler);
        $reflectionProperty = $reflectionClass->getProperty('connections');
        /** @var SplObjectStorage<ConnectionInterface, null> $connections */
        $connections = $reflectionProperty->getValue($statusHandler);

        $this->assertTrue($connections->offsetExists($testConnection));
    }

    public function testOnMessageDoesNothing(): void
    {
        $from = $this->createMock(ConnectionInterface::class);
        $msg = 'test message';

        // Expect no interactions with the output or connection
        $this->output->expects($this->never())->method('writeln');
        $from->expects($this->never())->method('send');

        $this->statusHandler->onMessage($from, $msg);
    }

    public function testOnCloseDetachesConnectionAndWritesToOutput(): void
    {
        $testConnection = new TestConnection();
        $testConnection->resourceId = 123;

        // First, attach the connection
        $this->statusHandler->onOpen($testConnection);

        $this->output->expects($this->once())
            ->method('writeln')
            ->with('Connection 123 has disconnected', OutputInterface::VERBOSITY_VERBOSE);

        $this->statusHandler->onClose($testConnection);

        $reflectionClass = new ReflectionClass($this->statusHandler);
        $reflectionProperty = $reflectionClass->getProperty('connections');
        /** @var SplObjectStorage<ConnectionInterface, null> $connections */
        $connections = $reflectionProperty->getValue($this->statusHandler);

        $this->assertFalse($connections->offsetExists($testConnection));
    }

    public function testOnCloseWithoutOutputDoesNotWrite(): void
    {
        $statusHandler = new StatusHandler();
        $testConnection = new TestConnection();
        $testConnection->resourceId = 123;

        $statusHandler->onOpen($testConnection); // Attach first

        $statusHandler->onClose($testConnection);

        $reflectionClass = new ReflectionClass($statusHandler);
        $reflectionProperty = $reflectionClass->getProperty('connections');
        /** @var SplObjectStorage<ConnectionInterface, null> $connections */
        $connections = $reflectionProperty->getValue($statusHandler);

        $this->assertFalse($connections->offsetExists($testConnection));
    }

    public function testOnErrorWritesToOutputAndClosesConnection(): void
    {
        $conn = $this->createMock(ConnectionInterface::class);
        $exception = new Exception('Test error message');

        $this->output->expects($this->once())
            ->method('writeln')
            ->with('An error has occurred: Test error message', OutputInterface::VERBOSITY_VERBOSE);

        $conn->expects($this->once())->method('close');

        $this->statusHandler->onError($conn, $exception);
    }

    public function testOnErrorWithoutOutputDoesNotWrite(): void
    {
        $statusHandler = new StatusHandler();
        $conn = $this->createMock(ConnectionInterface::class);
        $exception = new Exception('Test error message');

        $this->output->expects($this->never())->method('writeln');
        $conn->expects($this->once())->method('close');

        $statusHandler->onError($conn, $exception);
    }

    public function testBroadcastSendsMessageToAllConnections(): void
    {
        $conn1 = $this->createMock(ConnectionInterface::class);
        $conn2 = $this->createMock(ConnectionInterface::class);

        // Attach connections
        $this->statusHandler->onOpen($conn1);
        $this->statusHandler->onOpen($conn2);

        $message = '{"type": "status", "data": "running"}';

        $conn1->expects($this->once())
            ->method('send')
            ->with($message);
        $conn2->expects($this->once())
            ->method('send')
            ->with($message);

        $this->statusHandler->broadcast($message);
    }

    public function testBroadcastWithNoConnectionsDoesNothing(): void
    {
        // No connections are attached by default in a fresh setup
        $message = '{"type": "status", "data": "running"}';

        // Ensure no connections are sent messages
        $this->output->expects($this->never())->method('writeln'); // No output expected for broadcast itself

        $this->statusHandler->broadcast($message);
    }
}
