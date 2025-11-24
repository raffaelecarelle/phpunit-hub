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
        echo "Received message from {$from->resourceId}: {$msg}\n";
    }

    public function onClose(ConnectionInterface $conn): void
    {
        unset($this->connections[$conn]);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Broadcasts a message to all connected clients.
     *
     * @param string $message
     */
    public function broadcast(string $message): void
    {
        foreach ($this->connections as $conn) {
            $conn->send($message);
        }
    }
}
