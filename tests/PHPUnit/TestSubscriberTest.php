<?php

namespace PhpUnitHub\Tests\PHPUnit;

use PHPUnit\Event\Code\ClassMethod;
use PHPUnit\Event\Code\IssueTrigger\TestTrigger;
use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\TestDox;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Code\Throwable;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryMeter;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\Php83GarbageCollectorStatusProvider;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\Event\Telemetry\System;
use PHPUnit\Event\Telemetry\SystemMemoryMeter;
use PHPUnit\Event\Telemetry\SystemStopWatch;
use PHPUnit\Event\Test\DeprecationTriggered;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\MarkedIncomplete;
use PHPUnit\Event\Test\Passed;
use PHPUnit\Event\Test\PhpDeprecationTriggered;
use PHPUnit\Event\Test\PhpWarningTriggered;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\WarningTriggered;
use PHPUnit\Event\TestData\TestDataCollection;
use PHPUnit\Event\TestRunner\Finished as TestRunnerFinished;
use PHPUnit\Event\TestSuite\Started as TestSuiteStarted;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Metadata\MetadataCollection;
use PhpUnitHub\Http\DecoratedHttpServer;
use PhpUnitHub\PHPUnit\TestSubscriber;
use PHPUnit\Event\Code\TestCollection;

#[CoversClass(TestSubscriber::class)]
class TestSubscriberTest extends TestCase
{
    private TestSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new TestSubscriber();
    }

    public function testNotifyHandlesPreparedEvent(): void
    {
        $test = $this->createTest('TestName');
        $event = new Prepared($this->createTelemetryInfo(), $test);

        $this->expectOutputRegex('/"event":"test\.prepared","data":{"test":"TestName"}/');
        $this->subscriber->notify($event);
    }

    public function testNotifyHandlesPassedEvent(): void
    {
        $test = $this->createTest('TestName');
        $event = new Passed($this->createTelemetryInfo(), $test);

        $this->expectOutputRegex('/"event":"test\.passed","data":{"test":"TestName"}/');
        $this->subscriber->notify($event);
    }

    public function testNotifyHandlesFailedEvent(): void
    {
        $test = $this->createTest('TestName');
        $throwableMock = $this->createThrowableMock('Failure Message', 'Trace Data');
        $event = new Failed($this->createTelemetryInfo(), $test, $throwableMock, null);

        $this->expectOutputRegex('/"event":"test\.failed","data":.*"message":"Failure Message".*"trace":"Trace Data"/');
        $this->subscriber->notify($event);
    }

    public function testNotifyHandlesErroredEvent(): void
    {
        $test = $this->createTest('TestName');
        $throwableMock = $this->createThrowableMock('Error Message', 'Error Trace');
        $event = new Errored($this->createTelemetryInfo(), $test, $throwableMock);

        $this->expectOutputRegex('/"event":"test\.errored","data":.*"message":"Error Message".*"trace":"Error Trace"/');
        $this->subscriber->notify($event);
    }

    public function testNotifyHandlesSkippedEvent(): void
    {
        $test = $this->createTest('TestName');
        $event = new Skipped($this->createTelemetryInfo(), $test, 'Skip reason');

        $this->expectOutputRegex('/"event":"test\.skipped","data":.*"message":"Skip reason"/');
        $this->subscriber->notify($event);
    }

    public function testNotifyHandlesMarkedIncompleteEvent(): void
    {
        $test = $this->createTest('TestName');
        $throwableMock = $this->createThrowableMock('Incomplete Message');
        $event = new MarkedIncomplete($this->createTelemetryInfo(), $test, $throwableMock);

        $this->expectOutputRegex('/"event":"test\.incomplete","data":.*"message":"Incomplete Message"/');
        $this->subscriber->notify($event);
    }

    public function testNotifyHandlesWarningTriggeredEvent(): void
    {
        $test = $this->createTest('TestName');
        $event = new WarningTriggered($this->createTelemetryInfo(), $test, 'Warning Message', 'file.php', 1, false, '');

        $this->expectOutputRegex('/"event":"test\.warning","data":.*"message":"Warning Message"/');
        $this->subscriber->notify($event);
    }

    public function testNotifyHandlesDeprecationTriggeredEvent(): void
    {
        $test = $this->createTest('TestName');
        $event = new DeprecationTriggered($this->createTelemetryInfo(), $test, 'Deprecation Message', 'file.php', 1, false, false, false, TestTrigger::self(), '');

        $this->expectOutputRegex('/"event":"test\.deprecation","data":.*"message":"Deprecation Message"/');
        $this->subscriber->notify($event);
    }

    public function testNotifyHandlesPhpDeprecationTriggeredEvent(): void
    {
        $test = $this->createTest('TestName');
        $event = new PhpDeprecationTriggered($this->createTelemetryInfo(), $test, 'PHP Deprecation Message', 'file.php', 1, false, false, false, TestTrigger::self());

        $this->expectOutputRegex('/"event":"test\.deprecation","data":.*"message":"PHP Deprecation Message"/');
        $this->subscriber->notify($event);
    }

    public function testNotifyHandlesPhpWarningTriggeredEvent(): void
    {
        $test = $this->createTest('TestName');
        $event = new PhpWarningTriggered($this->createTelemetryInfo(), $test, 'PHP Warning Message', 'file.php', 1, false, '');

        $this->expectOutputRegex('/"event":"test\.warning","data":.*"message":"PHP Warning Message"/');
        $this->subscriber->notify($event);
    }

    public function testNotifyHandlesTestSuiteStartedEvent(): void
    {
        $suiteMock = $this->createMock(\PHPUnit\Event\TestSuite\TestSuite::class);
        $testCollectionMock = TestCollection::fromArray([$this->createTest('Test1'), $this->createTest('Test1')]);

        $suiteMock->method('isForTestClass')->willReturn(true);
        $suiteMock->method('name')->willReturn('SuiteName');
        $suiteMock->method('tests')->willReturn($testCollectionMock);

        $event = new TestSuiteStarted($this->createTelemetryInfo(), $suiteMock);

        $this->expectOutputRegex('/"event":"suite\.started","data":.*"name":"SuiteName".*"count":2/');
        $this->subscriber->notify($event);
    }

    public function testNotifyHandlesTestRunnerFinishedEvent(): void
    {
        $event = new TestRunnerFinished($this->createTelemetryInfo());

        $this->expectOutputRegex('/"event":"execution\.ended","data":.*"summary"/');
        $this->subscriber->notify($event);
    }

    public function testNotifyHandlesTestFinishedEvent(): void
    {
        $test = $this->createTest('TestName');
        $event = new Finished($this->createTelemetryInfo(), $test, 3);

        $this->expectOutputRegex('/"event":"test\.finished","data":.*"assertions":3/');
        $this->subscriber->notify($event);
    }

    private function createTest(string $name): Test
    {
        return new TestMethod(
            'TestClass',
            $name,
            '/path/to/TestClass.php',
            123,
            new TestDox('TestName', $name, ''),
            MetadataCollection::fromArray([]),
            TestDataCollection::fromArray([])
        );
    }

    private function createTelemetryInfo(): Info
    {
        $stoWatch = new SystemStopWatch();
        $memoryMeter = new SystemMemoryMeter();
        $garbage = new Php83GarbageCollectorStatusProvider();
        $system = new System($stoWatch, $memoryMeter, $garbage);

        $hrTime = HRTime::fromSecondsAndNanoseconds(123, 456);
        $memoryUsage = MemoryUsage::fromBytes(1024);

        $snapshot = new Snapshot($hrTime, $memoryUsage, $memoryUsage, $garbage->status());

        return new Info($system->snapshot(), $stoWatch->current()->duration($hrTime), $memoryMeter->memoryUsage(), $snapshot->time()->duration($hrTime), $snapshot->memoryUsage());
    }

    private function createThrowableMock(string $message, string $trace = ''): Throwable
    {
        return new Throwable('', $message, '', $trace, null);
    }
}
