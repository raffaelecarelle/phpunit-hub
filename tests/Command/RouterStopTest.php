<?php

namespace PHPUnitGUI\Tests\Command;

use PHPUnit\Framework\TestCase;
use PHPUnitGUI\Command\Router;
use PHPUnitGUI\Parser\JUnitParser;
use PHPUnitGUI\Discoverer\TestDiscoverer;
use PHPUnitGUI\TestRunner\TestRunner;
use PHPUnitGUI\WebSocket\StatusHandler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use React\ChildProcess\Process;

class RouterStopTest extends TestCase
{
    public function testStopEndpointTerminatesProcessAndReturns202(): void
    {
        $mockHttpServer = $this->createMock(HttpServerInterface::class);
        $mockOutput = $this->createMock(OutputInterface::class);
        $mockStatusHandler = $this->createMock(StatusHandler::class);
        $mockTestRunner = $this->createMock(TestRunner::class);
        $jUnitParser = $this->createMock(JUnitParser::class);
        $testDiscoverer = $this->createMock(TestDiscoverer::class);

        $router = new Router($mockHttpServer, $mockOutput, $mockStatusHandler, $mockTestRunner, $jUnitParser, $testDiscoverer);

        // Create a mock Process that expects terminate() to be called
        $mockProcess = $this->createMock(Process::class);
        $mockProcess->expects($this->once())
            ->method('terminate')
            ->with();

        // Set private properties via reflection: isTestRunning = true, currentProcess = mockProcess, currentRunId
        $reflectionObject = new \ReflectionObject($router);
        $reflectionProperty = $reflectionObject->getProperty('isTestRunning');
        $reflectionProperty->setValue($router, true);

        $propCurrentProcess = $reflectionObject->getProperty('currentProcess');
        $propCurrentProcess->setValue($router, $mockProcess);

        $propRunId = $reflectionObject->getProperty('currentRunId');
        $propRunId->setValue($router, 'test-run-id');

        // Prepare a mock Request that returns path '/api/stop' and method 'POST'
        $mockUri = $this->createMock(UriInterface::class);
        $mockUri->method('getPath')->willReturn('/api/stop');

        $mockRequest = $this->createMock(RequestInterface::class);
        $mockRequest->method('getUri')->willReturn($mockUri);
        $mockRequest->method('getMethod')->willReturn('POST');

        // Mock the ConnectionInterface to capture the sent response
        $sent = [];
        $mockConnection = $this->createMock(ConnectionInterface::class);
        $mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($msg) use (&$sent) {
                // record message for further assertions
                $sent[] = $msg;
                return true;
            }));
        $mockConnection->expects($this->once())->method('close');

        // Call onOpen which will route the request
        $router->onOpen($mockConnection, $mockRequest);

        // Assert that a response was sent and that it contains HTTP/1.1 202
        $this->assertNotEmpty($sent, 'No response sent');
        $this->assertStringContainsString('HTTP/1.1 202', $sent[0]);
    }
}
