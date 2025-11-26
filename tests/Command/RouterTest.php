<?php

namespace PhpUnitHub\Tests\Command;

use PHPUnit\Framework\TestCase;
use PhpUnitHub\Command\Router;
use PhpUnitHub\Parser\JUnitParser;
use PhpUnitHub\Discoverer\TestDiscoverer;
use PhpUnitHub\TestRunner\TestRunner;
use PhpUnitHub\WebSocket\StatusHandler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use React\ChildProcess\Process;

class RouterTest extends TestCase
{
    private function createRouter(): Router
    {
        $mockHttpServer = $this->createMock(HttpServerInterface::class);
        $mockOutput = $this->createMock(OutputInterface::class);
        $mockStatusHandler = $this->createMock(StatusHandler::class);
        $mockTestRunner = $this->createMock(TestRunner::class);
        $jUnitParser = $this->createMock(JUnitParser::class);
        $testDiscoverer = $this->createMock(TestDiscoverer::class);

        return new Router($mockHttpServer, $mockOutput, $mockStatusHandler, $mockTestRunner, $jUnitParser, $testDiscoverer);
    }

    private function createMockProcess(bool $expectTerminate = false): Process
    {
        $mockProcess = $this->createMock(Process::class);
        $mockProcess->method('isRunning')->willReturn(true); // Assume it's running for the test
        if ($expectTerminate) {
            $mockProcess->expects($this->once())
                ->method('terminate');
        } else {
            $mockProcess->expects($this->never())
                ->method('terminate');
        }

        return $mockProcess;
    }

    private function createMockRequest(string $path, string $method): RequestInterface
    {
        $mockUri = $this->createMock(UriInterface::class);
        $mockUri->method('getPath')->willReturn($path);

        $mockRequest = $this->createMock(RequestInterface::class);
        $mockRequest->method('getUri')->willReturn($mockUri);
        $mockRequest->method('getMethod')->willReturn($method);
        return $mockRequest;
    }

    private function createMockConnection(array &$sentMessages): ConnectionInterface
    {
        $mockConnection = $this->createMock(ConnectionInterface::class);
        $mockConnection->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($msg) use (&$sentMessages) {
                $sentMessages[] = $msg;
                return true;
            }));
        $mockConnection->expects($this->once())->method('close');
        return $mockConnection;
    }

    public function testStopAllEndpointTerminatesAllProcesses(): void
    {
        $router = $this->createRouter();

        $process = $this->createMockProcess(true);
        $mockProcess2 = $this->createMockProcess(true);

        $reflectionObject = new \ReflectionObject($router);
        $reflectionProperty = $reflectionObject->getProperty('runningProcesses');
        $reflectionProperty->setValue($router, [
            'run-id-1' => $process,
            'run-id-2' => $mockProcess2,
        ]);

        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest('/api/stop', 'POST');

        $router->onOpen($mockConnection, $mockRequest);

        $this->assertNotEmpty($sent, 'No response sent');
        $this->assertStringContainsString('HTTP/1.1 202', $sent[0]);
    }

    public function testStopSingleEndpointTerminatesSpecificProcess(): void
    {
        $router = $this->createRouter();

        $process = $this->createMockProcess(true);
        $mockProcessToKeep = $this->createMockProcess(false); // Should not be terminated

        $reflectionObject = new \ReflectionObject($router);
        $reflectionProperty = $reflectionObject->getProperty('runningProcesses');
        $reflectionProperty->setValue($router, [
            'run-id-to-terminate' => $process,
            'run-id-to-keep' => $mockProcessToKeep,
        ]);

        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest('/api/stop-single-test/run-id-to-terminate', 'POST');

        $router->onOpen($mockConnection, $mockRequest);

        $this->assertNotEmpty($sent, 'No response sent');
        $this->assertStringContainsString('HTTP/1.1 202', $sent[0]);
    }

    public function testStopEndpointReturns400IfNoProcessIsRunning(): void
    {
        $router = $this->createRouter();

        // runningProcesses is empty by default

        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest('/api/stop', 'POST');

        $router->onOpen($mockConnection, $mockRequest);

        $this->assertNotEmpty($sent, 'No response sent');
        $this->assertStringContainsString('HTTP/1.1 400', $sent[0]);
        $this->assertStringContainsString('No test run in progress.', $sent[0]);
    }

    public function testStopSingleEndpointReturns404IfRunIdNotFound(): void
    {
        $router = $this->createRouter();

        $mockProcess = $this->createMockProcess(false); // No terminate expected
        $reflectionObject = new \ReflectionObject($router);
        $reflectionProperty = $reflectionObject->getProperty('runningProcesses');
        $reflectionProperty->setValue($router, [
            'some-other-run-id' => $mockProcess,
        ]);

        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest('/api/stop-single-test/non-existent-run-id', 'POST');

        $router->onOpen($mockConnection, $mockRequest);

        $this->assertNotEmpty($sent, 'No response sent');
        $this->assertStringContainsString('HTTP/1.1 404', $sent[0]);
        $this->assertStringContainsString('No test run found with ID non-existent-run-id.', $sent[0]);
    }
}
