<?php

namespace PHPUnitGUI\WebSocket;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SplObjectStorage;

class StatusHandler implements MessageComponentInterface
{
    private SplObjectStorage $connections;

    public function __construct()
    {
        $this->connections = new SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->connections[$conn] = true;
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        // For now, we don't handle incoming messages from clients
        echo sprintf('Received message from %s: %s%s', $from->resourceId, $msg, PHP_EOL);
    }

    public function onClose(ConnectionInterface $conn): void
    {
        unset($this->connections[$conn]);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo sprintf('An error has occurred: %s%s', $e->getMessage(), PHP_EOL);
        $conn->close();
    }

    /**
     * Broadcasts a message to all connected clients.
     */
    public function broadcast(string $message): void
    {
        foreach ($this->connections as $connection) {
            $connection->send($message);
        }
    }
}
