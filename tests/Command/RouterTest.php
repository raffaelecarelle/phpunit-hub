<?php

namespace PhpUnitHub\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PhpUnitHub\Coverage\Coverage;
use Psr\Http\Message\StreamInterface;
use ReflectionObject;
use PHPUnit\Framework\TestCase;
use PhpUnitHub\Command\Router;
use PhpUnitHub\Discoverer\TestDiscoverer;
use PhpUnitHub\TestRunner\TestRunner;
use PhpUnitHub\WebSocket\StatusHandler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use React\ChildProcess\Process;
use React\Stream\ReadableStreamInterface;

#[CoversClass(Router::class)]
class RouterTest extends TestCase
{
    private function createRouter(
        ?TestRunner $testRunner = null,
        ?TestDiscoverer $testDiscoverer = null,
        string $projectRoot = '/path/to/project'
    ): Router {
        $mockHttpServer = $this->createMock(HttpServerInterface::class);
        $mockOutput = $this->createMock(OutputInterface::class);
        $mockStatusHandler = $this->createMock(StatusHandler::class);
        $mockTestRunner = $testRunner ?? $this->createMock(TestRunner::class);
        $mockTestDiscoverer = $testDiscoverer ?? $this->createMock(TestDiscoverer::class);

        return new Router($mockHttpServer, $mockOutput, $mockStatusHandler, $mockTestRunner, $mockTestDiscoverer, $projectRoot);
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

    private function createRunnableMockProcess(): Process
    {
        $mockProcess = $this->createMock(Process::class);
        $mockStream = $this->createMock(ReadableStreamInterface::class);
        $mockStream->method('on')->willReturn($mockStream);

        $mockProcess->stdout = $mockStream;
        $mockProcess->stderr = $mockStream;

        return $mockProcess;
    }

    private function createMockRequest(string $path, string $method, string $body = '', string $query = ''): RequestInterface
    {
        $mockUri = $this->createMock(UriInterface::class);
        $mockUri->method('getPath')->willReturn($path);
        $mockUri->method('getQuery')->willReturn($query);

        $mockBody = $this->createMock(StreamInterface::class);
        $mockBody->method('getContents')->willReturn($body);

        $mockRequest = $this->createMock(RequestInterface::class);
        $mockRequest->method('getUri')->willReturn($mockUri);
        $mockRequest->method('getMethod')->willReturn($method);
        $mockRequest->method('getBody')->willReturn($mockBody);
        return $mockRequest;
    }

    /**
     * @param array<empty> $sentMessages
     */
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

    public function testRunTestsEndpointStartsProcess(): void
    {
        $testRunner = $this->createMock(TestRunner::class);
        $testRunner->expects($this->once())->method('run')->willReturn($this->createRunnableMockProcess());
        $router = $this->createRouter($testRunner);

        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest('/api/run', 'POST', '{"filters":[]}');

        $router->onOpen($mockConnection, $mockRequest);

        $this->assertStringContainsString('HTTP/1.1 202', $sent[0]);
    }

    public function testDiscoverTestsEndpointReturnsDiscovererResult(): void
    {
        $testDiscoverer = $this->createMock(TestDiscoverer::class);
        $testDiscoverer->method('discover')->willReturn(['suites' => ['MySuite']]);
        $router = $this->createRouter(null, $testDiscoverer);

        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest('/api/tests', 'GET');

        $router->onOpen($mockConnection, $mockRequest);

        $this->assertStringContainsString('HTTP/1.1 200', $sent[0]);
        $this->assertStringContainsString('{"suites":["MySuite"]}', $sent[0]);
    }

    public function testStopAllEndpointTerminatesAllProcesses(): void
    {
        $router = $this->createRouter();

        $process = $this->createMockProcess(true);
        $mockProcess2 = $this->createMockProcess(true);

        $reflectionObject = new ReflectionObject($router);
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

        $reflectionObject = new ReflectionObject($router);
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
        $reflectionObject = new ReflectionObject($router);
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

    public function testGetCoverageReturnsCoverageData(): void
    {
        $coverageMock = $this->createMock(Coverage::class);
        $coverageMock->method('parse')->willReturn(['total_coverage_percent' => 50.0]);

        $router = $this->createRouter();
        $reflectionObject = new ReflectionObject($router);
        $reflectionProperty = $reflectionObject->getProperty('coverage');
        $reflectionProperty->setValue($router, $coverageMock);

        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest('/api/coverage/some-run-id', 'GET');

        $router->onOpen($mockConnection, $mockRequest);

        $this->assertNotEmpty($sent, 'No response sent');
        $this->assertStringContainsString('HTTP/1.1 200', $sent[0]);
        $this->assertStringContainsString('{"total_coverage_percent":50}', $sent[0]);
    }

    public function testGetFileCoverageReturnsCoverageData(): void
    {
        $projectRoot = sys_get_temp_dir();
        $router = $this->createRouter(null, null, $projectRoot);

        $filePath = 'some/file.php';
        $fullPath = $projectRoot . '/' . $filePath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        touch($fullPath);

        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest(
            '/api/file-coverage',
            'GET',
            '',
            'runId=some-run-id&path=' . $filePath
        );

        $router->onOpen($mockConnection, $mockRequest);

        $this->assertNotEmpty($sent, 'No response sent');
        $this->assertStringContainsString('HTTP/1.1 200', $sent[0]);
        $this->assertStringContainsString('{"lines":[]}', $sent[0]);

        unlink($fullPath);
        rmdir($dir);
    }

    public function testGetFileContentReturnsFileContent(): void
    {
        $router = $this->createRouter(null, null, sys_get_temp_dir());

        $sent = [];
        $mockConnection = $this->createMockConnection($sent);

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test content');

        $mockRequest = $this->createMockRequest(
            '/api/file-content',
            'GET',
            '',
            'path=' . $tempFile
        );

        $router->onOpen($mockConnection, $mockRequest);

        $this->assertNotEmpty($sent, 'No response sent');
        $this->assertStringContainsString('HTTP/1.1 200', $sent[0]);
        $this->assertStringContainsString('test content', $sent[0]);

        unlink($tempFile);
    }

    public function testGetLastRunContextReturnsCorrectData(): void
    {
        $testRunner = $this->createMock(TestRunner::class);
        $testRunner->expects($this->once())->method('run')->willReturn($this->createRunnableMockProcess());
        $router = $this->createRouter($testRunner);

        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest('/api/run', 'POST', '{"filters":["MyTest"],"groups":["MyGroup"],"options":{"stopOnFailure":true},"suites":["MySuite"]}');

        $router->onOpen($mockConnection, $mockRequest);

        $this->assertEquals(['MyTest'], $router->getLastFilters());
        $this->assertEquals(['MyGroup'], $router->getLastGroups());
        $this->assertEquals(['stopOnFailure' => true], $router->getLastOptions());
        $this->assertEquals(['MySuite'], $router->getLastSuites());
    }
}
