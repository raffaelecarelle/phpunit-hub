<?php

namespace PhpUnitHub\Tests\Command;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PhpUnitHub\Command\Router;
use PhpUnitHub\Coverage\Coverage;
use PhpUnitHub\Discoverer\TestDiscoverer;
use PhpUnitHub\TestRunner\TestRunner;
use PhpUnitHub\WebSocket\StatusHandler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use React\ChildProcess\Process;
use React\Stream\ReadableStreamInterface;
use ReflectionClass;
use ReflectionObject;
use Symfony\Component\Console\Output\OutputInterface;

use function dirname;

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
            mkdir($dir, 0o777, true);
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

    public function testOnMessageDelegatesToWebSocket(): void
    {
        $mockHttpServer = $this->createMock(HttpServerInterface::class);
        $mockHttpServer->expects($this->once())->method('onMessage');

        $router = new Router(
            $mockHttpServer,
            $this->createMock(OutputInterface::class),
            $this->createMock(StatusHandler::class),
            $this->createMock(TestRunner::class),
            $this->createMock(TestDiscoverer::class),
            '/path/to/project'
        );

        $mockConnection = $this->createMock(ConnectionInterface::class);
        /** @phpstan-ignore-next-line */
        $mockConnection->WebSocket = true;
        // Simulate a connection with WebSocket property
        $router->onMessage($mockConnection, 'test message');
    }

    public function testOnMessageDoesNotThrowWhenWebSocketPropertyIsNull(): void
    {
        $mockHttpServer = $this->createMock(HttpServerInterface::class);
        $mockHttpServer->expects($this->never())->method('onMessage');

        $router = new Router(
            $mockHttpServer,
            $this->createMock(OutputInterface::class),
            $this->createMock(StatusHandler::class),
            $this->createMock(TestRunner::class),
            $this->createMock(TestDiscoverer::class),
            '/path/to/project'
        );

        $mockConnection = $this->createMock(ConnectionInterface::class);
        /** @phpstan-ignore-next-line */
        $mockConnection->WebSocket = null;
        // Simulate a connection without WebSocket property
        $router->onMessage($mockConnection, 'test message');
    }

    public function testOnCloseCallsHttpServerOnCloseWhenWebSocketIsNotNull(): void
    {
        $mockHttpServer = $this->createMock(HttpServerInterface::class);
        $mockHttpServer->expects($this->once())->method('onClose');

        $router = new Router(
            $mockHttpServer,
            $this->createMock(OutputInterface::class),
            $this->createMock(StatusHandler::class),
            $this->createMock(TestRunner::class),
            $this->createMock(TestDiscoverer::class),
            '/path/to/project'
        );

        $mockConnection = $this->createMock(ConnectionInterface::class);
        /** @phpstan-ignore-next-line */
        $mockConnection->WebSocket = true;
        // Simulate a connection with WebSocket property
        $router->onClose($mockConnection);
    }

    public function testOnCloseDoesNotThrowWhenWebSocketPropertyIsNull(): void
    {
        $mockHttpServer = $this->createMock(HttpServerInterface::class);
        $mockHttpServer->expects($this->never())->method('onClose');

        $router = new Router(
            $mockHttpServer,
            $this->createMock(OutputInterface::class),
            $this->createMock(StatusHandler::class),
            $this->createMock(TestRunner::class),
            $this->createMock(TestDiscoverer::class),
            '/path/to/project'
        );

        $mockConnection = $this->createMock(ConnectionInterface::class);
        /** @phpstan-ignore-next-line */
        $mockConnection->WebSocket = null;
        // Simulate a connection without WebSocket property
        $router->onClose($mockConnection);
    }

    public function testOnErrorHandlesWebSocketError(): void
    {
        $mockHttpServer = $this->createMock(HttpServerInterface::class);
        $mockHttpServer->expects($this->once())->method('onError');

        $router = new Router(
            $mockHttpServer,
            $this->createMock(OutputInterface::class),
            $this->createMock(StatusHandler::class),
            $this->createMock(TestRunner::class),
            $this->createMock(TestDiscoverer::class),
            '/path/to/project'
        );

        $mockConnection = $this->createMock(ConnectionInterface::class);
        /** @phpstan-ignore-next-line */
        $mockConnection->WebSocket = true; // Simulate a WebSocket connection

        $exception = new Exception('Test WebSocket error');
        $router->onError($mockConnection, $exception);
    }

    public function testOnErrorHandlesHttpConnectionError(): void
    {
        $mockHttpServer = $this->createMock(HttpServerInterface::class);
        $mockHttpServer->expects($this->never())->method('onError');

        $mockOutput = $this->createMock(OutputInterface::class);
        $mockOutput->expects($this->once())
            ->method('writeln')
            ->with($this->stringContains('HTTP Server error: Test HTTP error'));

        $router = new Router(
            $mockHttpServer,
            $mockOutput,
            $this->createMock(StatusHandler::class),
            $this->createMock(TestRunner::class),
            $this->createMock(TestDiscoverer::class),
            '/path/to/project'
        );

        $mockConnection = $this->createMock(ConnectionInterface::class);
        /** @phpstan-ignore-next-line */
        $mockConnection->WebSocket = null; // Simulate an HTTP connection without WebSocket

        $mockConnection->expects($this->once())->method('close');

        $exception = new Exception('Test HTTP error');
        $router->onError($mockConnection, $exception);
    }

    public function testWebSocketConnection(): void
    {
        $mockHttpServer = $this->createMock(HttpServerInterface::class);
        $mockHttpServer->expects($this->once())->method('onOpen');

        $router = new Router(
            $mockHttpServer,
            $this->createMock(OutputInterface::class),
            $this->createMock(StatusHandler::class),
            $this->createMock(TestRunner::class),
            $this->createMock(TestDiscoverer::class),
            '/path/to/project'
        );

        $mockConnection = $this->createMock(ConnectionInterface::class);
        $mockRequest = $this->createMockRequest('/ws/status', 'GET');

        $router->onOpen($mockConnection, $mockRequest);
    }

    public function testRunFailedEndpointWithNoFailedTests(): void
    {
        $router = $this->createRouter();

        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest('/api/run-failed', 'POST', '{}');

        $router->onOpen($mockConnection, $mockRequest);

        $this->assertStringContainsString('HTTP/1.1 400', $sent[0]);
        $this->assertStringContainsString('No failed tests to run.', $sent[0]);
    }

    public function testServesIndexHtml(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $router = $this->createRouter(null, null, $projectRoot);

        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest('/', 'GET');

        $router->onOpen($mockConnection, $mockRequest);

        $this->assertStringContainsString('HTTP/1.1 200', $sent[0]);
        $this->assertStringContainsString('Content-Type: text/html', $sent[0]);
        $this->assertStringContainsString("window.WS_HOST = '127.0.0.1'", $sent[0]);
        $this->assertStringContainsString("window.WS_PORT = '8080'", $sent[0]);
    }

    public function testNotFoundResponse(): void
    {
        $router = $this->createRouter();

        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest('/non-existent-route', 'GET');

        $router->onOpen($mockConnection, $mockRequest);

        $this->assertStringContainsString('HTTP/1.1 404', $sent[0]);
        $this->assertStringContainsString('Not Found', $sent[0]);
    }

    public function testGetMimeType(): void
    {
        $router = $this->createRouter();
        $reflectionClass = new ReflectionClass($router);
        $reflectionMethod = $reflectionClass->getMethod('getMimeType');

        $this->assertEquals('text/html; charset=utf-8', $reflectionMethod->invoke($router, 'file.html'));
        $this->assertEquals('text/css; charset=utf-8', $reflectionMethod->invoke($router, 'file.css'));
        $this->assertEquals('application/javascript; charset=utf-8', $reflectionMethod->invoke($router, 'file.js'));
        $this->assertEquals('text/plain', $reflectionMethod->invoke($router, 'file.txt'));
    }

    public function testGetFileCoverageErrorResponses(): void
    {
        $router = $this->createRouter(null, null, '/path/to/project');

        // No file path
        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest('/api/file-coverage', 'GET', '', 'runId=123');
        $router->onOpen($mockConnection, $mockRequest);
        $this->assertStringContainsString('HTTP/1.1 400', $sent[0]);
        $this->assertStringContainsString('File path not provided.', $sent[0]);

        // No run ID
        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest('/api/file-coverage', 'GET', '', 'path=some/file.php');
        $router->onOpen($mockConnection, $mockRequest);
        $this->assertStringContainsString('HTTP/1.1 400', $sent[0]);
        $this->assertStringContainsString('Run ID not provided.', $sent[0]);

        // Access denied
        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest('/api/file-coverage', 'GET', '', 'runId=123&path=../outside/file.php');
        $router->onOpen($mockConnection, $mockRequest);
        $this->assertStringContainsString('HTTP/1.1 403', $sent[0]);
        $this->assertStringContainsString('Access denied.', $sent[0]);
    }

    public function testGetFileContentErrorResponses(): void
    {
        $router = $this->createRouter(null, null, '/path/to/project');

        // No file path
        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest('/api/file-content', 'GET');
        $router->onOpen($mockConnection, $mockRequest);
        $this->assertStringContainsString('HTTP/1.1 400', $sent[0]);
        $this->assertStringContainsString('File path not provided.', $sent[0]);

        // Access denied
        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest('/api/file-content', 'GET', '', 'path=/etc/passwd');
        $router->onOpen($mockConnection, $mockRequest);
        $this->assertStringContainsString('HTTP/1.1 403', $sent[0]);
        $this->assertStringContainsString('Access denied.', $sent[0]);

        // File not found
        $sent = [];
        $mockConnection = $this->createMockConnection($sent);
        $mockRequest = $this->createMockRequest('/api/file-content', 'GET', '', 'path=/path/to/project/non-existent-file.php');
        $router->onOpen($mockConnection, $mockRequest);
        $this->assertStringContainsString('HTTP/1.1 404', $sent[0]);
        $this->assertStringContainsString('File not found.', $sent[0]);
    }
}
