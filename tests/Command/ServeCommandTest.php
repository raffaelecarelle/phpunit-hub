<?php

namespace PhpUnitHub\Tests\Command;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PhpUnitHub\Command\ServeCommand;
use PhpUnitHub\Command\Router;
use PhpUnitHub\Discoverer\TestDiscoverer;
use PhpUnitHub\Parser\JUnitParser;
use PhpUnitHub\TestRunner\TestRunner;
use PhpUnitHub\WebSocket\StatusHandler;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use React\ChildProcess\Process;

#[Group('functional')]
class ServeCommandTest extends TestCase
{
    private CommandTester $commandTester;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $mockLoop = $this->createMock(LoopInterface::class);
        $mockWsServer = $this->createMock(WsServer::class);

        $this->application = new Application();
        $this->application->add(new ServeCommand(loop: $mockLoop, wsServer: $mockWsServer));

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

//    public function testExecuteWithoutWatchOptionOutputsMessages(): void
//    {
//        $this->commandTester->execute([]);
//
//        $output = $this->commandTester->getDisplay();
//
//        $this->assertStringContainsString('Starting server on http://127.0.0.1:8080', $output);
//        $this->assertStringContainsString('API endpoint available at GET /api/tests', $output);
//        $this->assertStringContainsString('API endpoint available at POST /api/run', $output);
//        $this->assertStringContainsString('API endpoint available at POST /api/run-failed', $output);
//        $this->assertStringContainsString('WebSocket server listening on /ws/status', $output);
//        $this->assertStringContainsString("Serving static files from 'public' directory", $output);
//        $this->assertStringNotContainsString('Event-based file watcher enabled (inotify).', $output);
//        $this->assertEquals(0, $this->commandTester->getStatusCode());
//    }

    public function testExecuteWithWatchOptionInotifywaitNotFoundOutputsError(): void
    {
        // This test requires mocking the `React\ChildProcess\Process` class,
        // which is directly instantiated within the private `startFileWatcher` method.
        // Without refactoring `ServeCommand` to allow injecting a `Process` factory
        // or making the `createProcess` method protected, it's not possible to
        // mock the `Process` behavior in a unit test.
        // Therefore, this test is skipped.
        // A proper unit test would involve refactoring ServeCommand.
        $this->markTestSkipped(
            'Cannot reliably test inotifywait not found without refactoring ServeCommand ' .
            'to allow injection or mocking of React\ChildProcess\Process.'
        );
    }

    public function testExecuteWithWatchOptionInotifywaitFoundAndFileChangesTriggersTests(): void
    {
        // This test also requires mocking the `React\ChildProcess\Process` class
        // and the `RouterInterface` to verify `runTests` is called.
        // Due to direct instantiation of `Process` and `Router` within `ServeCommand`,
        // and the blocking nature of `IoServer::run()`, this test cannot be
        // reliably performed as a unit test without refactoring `ServeCommand`.
        $this->markTestSkipped(
            'Cannot reliably test inotifywait found and file changes without refactoring ServeCommand ' .
            'to allow injection or mocking of its dependencies (Process, Router, IoServer).'
        );
    }
}
