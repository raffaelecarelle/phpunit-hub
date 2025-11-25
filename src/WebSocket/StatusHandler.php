<?php

namespace PhpUnitHub\WebSocket;

use Exception;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SplObjectStorage;
use Symfony\Component\Console\Output\OutputInterface;

class StatusHandler implements MessageComponentInterface
{
    /** @var SplObjectStorage<ConnectionInterface, mixed> */
    private readonly SplObjectStorage $connections;

    public function __construct(private readonly ?OutputInterface $output = null)
    {
        $this->connections = new SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->connections->offsetSet($conn);
        /** @var \Ratchet\WebSocket\WsConnection $conn */
        // @phpstan-ignore-next-line
        $this->output?->writeln(sprintf('New connection! (%s)', $conn->resourceId), OutputInterface::VERBOSITY_VERBOSE);
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        // For now, we don't handle incoming messages from clients
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->connections->offsetUnset($conn);
        /** @var \Ratchet\WebSocket\WsConnection $conn */
        // @phpstan-ignore-next-line
        $this->output?->writeln(sprintf('Connection %s has disconnected', $conn->resourceId), OutputInterface::VERBOSITY_VERBOSE);
    }

    public function onError(ConnectionInterface $conn, Exception $e): void
    {
        $this->output?->writeln(sprintf('An error has occurred: %s', $e->getMessage()), OutputInterface::VERBOSITY_VERBOSE);
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
