<?php

namespace PhpUnitHub\Tests\Command;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\MockObject\MockObject;
use PhpUnitHub\Command\RouterInterface;
use PHPUnit\Framework\TestCase;
use PhpUnitHub\Command\ServeCommand;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversNothing]
class ServeCommandTest extends TestCase
{
    private CommandTester $commandTester;

    private Application $application;

    private LoopInterface&MockObject $mockLoop;

    private WsServer&MockObject $mockWsServer;

    private RouterInterface&MockObject $mockRouter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLoop = $this->createMock(LoopInterface::class);
        $this->mockWsServer = $this->createMock(WsServer::class);
        $this->mockRouter = $this->createMock(RouterInterface::class);

        $this->application = new Application();
        $this->application->add(new ServeCommand(
            loop: $this->mockLoop,
            wsServer: $this->mockWsServer,
            router: $this->mockRouter
        ));

        $command = $this->application->find('serve');
        $this->commandTester = new CommandTester($command);
    }

    public function testConfigure(): void
    {
        $command = $this->application->find('serve');

        $this->assertEquals('serve', $command->getName());
        $this->assertEquals('Starts the PHPUnit GUI server.', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('watch'));
        $inputOption = $command->getDefinition()->getOption('watch');
        $this->assertFalse($inputOption->isValueRequired());
        $this->assertFalse($inputOption->isValueOptional());
        $this->assertFalse($inputOption->acceptValue()); // Corrected assertion
        $this->assertEquals('Enable file watching to re-run tests on changes', $inputOption->getDescription());
    }

    public function testExecuteWithoutWatchOptionOutputsMessages(): void
    {
        $this->mockLoop->expects($this->once())->method('run');
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Starting server on http://127.0.0.1:8080', $output);
        $this->assertStringContainsString("Serving static files from 'public' directory", $output);
        $this->assertStringNotContainsString('Event-based file watcher enabled (inotify).', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithWatchOption(): void
    {
        $port = random_int(1024, 65535);
        $this->mockLoop->expects($this->once())->method('run');
        $this->commandTester->execute(['--watch' => true, '--port' => $port]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Starting server on http://127.0.0.1:' . $port, $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }
}
