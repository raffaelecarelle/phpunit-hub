<?php

namespace PHPUnitGUI\Tests\Unit\WebSocket;

use PHPUnit\Framework\TestCase;
use PHPUnitGUI\WebSocket\StatusHandler;
use Ratchet\ConnectionInterface;
use ReflectionClass;

class StatusHandlerTest extends TestCase
{
    private StatusHandler $statusHandler;

    protected function setUp(): void
    {
        $this->statusHandler = new StatusHandler();
    }

    /**
     * @return \SplObjectStorage<ConnectionInterface, mixed>
     */
    private function getConnections(): \SplObjectStorage
    {
        $reflectionClass = new ReflectionClass($this->statusHandler);
        $reflectionProperty = $reflectionClass->getProperty('connections');
        return $reflectionProperty->getValue($this->statusHandler);
    }

    public function testOnOpenAttachesConnection(): void
    {
        /** @var ConnectionInterface&\PHPUnit\Framework\MockObject\MockObject $conn */
        $conn = $this->createMock(ConnectionInterface::class);
        // @phpstan-ignore-next-line
        $conn->resourceId = 1; // Mock the property

        $this->assertCount(0, $this->getConnections());
        $this->statusHandler->onOpen($conn);
        $this->assertCount(1, $this->getConnections());
    }

    public function testOnCloseDetachesConnection(): void
    {
        /** @var ConnectionInterface&\PHPUnit\Framework\MockObject\MockObject $conn */
        $conn = $this->createMock(ConnectionInterface::class);
        // @phpstan-ignore-next-line
        $conn->resourceId = 1; // Mock the property

        $this->statusHandler->onOpen($conn);
        $this->assertCount(1, $this->getConnections());

        $this->statusHandler->onClose($conn);
        $this->assertCount(0, $this->getConnections());
    }

    public function testBroadcastSendsToAllConnections(): void
    {
        /** @var ConnectionInterface&\PHPUnit\Framework\MockObject\MockObject $conn1 */
        $conn1 = $this->createMock(ConnectionInterface::class);
        // @phpstan-ignore-next-line
        $conn1->resourceId = 1;
        $conn1->expects($this->once())->method('send')->with('Hello World');

        /** @var ConnectionInterface&\PHPUnit\Framework\MockObject\MockObject $conn2 */
        $conn2 = $this->createMock(ConnectionInterface::class);
        // @phpstan-ignore-next-line
        $conn2->resourceId = 2;
        $conn2->expects($this->once())->method('send')->with('Hello World');

        $this->statusHandler->onOpen($conn1);
        $this->statusHandler->onOpen($conn2);

        $this->statusHandler->broadcast('Hello World');
    }

    public function testBroadcastDoesNotSendToClosedConnections(): void
    {
        /** @var ConnectionInterface&\PHPUnit\Framework\MockObject\MockObject $conn1 */
        $conn1 = $this->createMock(ConnectionInterface::class);
        // @phpstan-ignore-next-line
        $conn1->resourceId = 1;
        $conn1->expects($this->once())->method('send')->with('Still here');

        /** @var ConnectionInterface&\PHPUnit\Framework\MockObject\MockObject $conn2 */
        $conn2 = $this->createMock(ConnectionInterface::class);
        // @phpstan-ignore-next-line
        $conn2->resourceId = 2;
        $conn2->expects($this->never())->method('send');

        $this->statusHandler->onOpen($conn1);
        $this->statusHandler->onOpen($conn2);

        $this->statusHandler->onClose($conn2);

        $this->statusHandler->broadcast('Still here');
    }
}
