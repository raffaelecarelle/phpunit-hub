<?php

namespace PhpUnitHub\Tests\PHPUnit;

use PHPUnit\Event\TestSuite\TestSuite;
use PHPUnit\Event\Code\IssueTrigger\TestTrigger;
use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\TestDox;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Code\Throwable;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
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
use PHPUnit\Framework\TestCase;
use PHPUnit\Metadata\MetadataCollection;
use PhpUnitHub\PHPUnit\TestSubscriber;
use PHPUnit\Event\Code\TestCollection;

#[CoversClass(TestSubscriber::class)]
class TestSubscriberTest extends TestCase
{
    private TestSubscriber $testSubscriber;

    protected function setUp(): void
    {
        $this->testSubscriber = new TestSubscriber();
    }

    public function testNotifyHandlesPreparedEvent(): void
    {
        $test = $this->createTest('TestName');
        $prepared = new Prepared($this->createTelemetryInfo(), $test);

        $this->expectOutputRegex('/"event":"test\.prepared","data":{"test":"TestName"}/');
        $this->testSubscriber->notify($prepared);
    }

    public function testNotifyHandlesPassedEvent(): void
    {
        $test = $this->createTest('TestName');
        $passed = new Passed($this->createTelemetryInfo(), $test);

        $this->expectOutputRegex('/"event":"test\.passed","data":{"test":"TestName"}/');
        $this->testSubscriber->notify($passed);
    }

    public function testNotifyHandlesFailedEvent(): void
    {
        $test = $this->createTest('TestName');
        $throwableMock = $this->createThrowableMock('Failure Message', 'Trace Data');
        $failed = new Failed($this->createTelemetryInfo(), $test, $throwableMock, null);

        $this->expectOutputRegex('/"event":"test\.failed","data":.*"message":"Failure Message".*"trace":"Trace Data"/');
        $this->testSubscriber->notify($failed);
    }

    public function testNotifyHandlesErroredEvent(): void
    {
        $test = $this->createTest('TestName');
        $throwableMock = $this->createThrowableMock('Error Message', 'Error Trace');
        $errored = new Errored($this->createTelemetryInfo(), $test, $throwableMock);

        $this->expectOutputRegex('/"event":"test\.errored","data":.*"message":"Error Message".*"trace":"Error Trace"/');
        $this->testSubscriber->notify($errored);
    }

    public function testNotifyHandlesSkippedEvent(): void
    {
        $test = $this->createTest('TestName');
        $skipped = new Skipped($this->createTelemetryInfo(), $test, 'Skip reason');

        $this->expectOutputRegex('/"event":"test\.skipped","data":.*"message":"Skip reason"/');
        $this->testSubscriber->notify($skipped);
    }

    public function testNotifyHandlesMarkedIncompleteEvent(): void
    {
        $test = $this->createTest('TestName');
        $throwableMock = $this->createThrowableMock('Incomplete Message');
        $markedIncomplete = new MarkedIncomplete($this->createTelemetryInfo(), $test, $throwableMock);

        $this->expectOutputRegex('/"event":"test\.incomplete","data":.*"message":"Incomplete Message"/');
        $this->testSubscriber->notify($markedIncomplete);
    }

    public function testNotifyHandlesWarningTriggeredEvent(): void
    {
        $test = $this->createTest('TestName');
        $warningTriggered = new WarningTriggered($this->createTelemetryInfo(), $test, 'Warning Message', 'file.php', 1, false, '');

        $this->expectOutputRegex('/"event":"test\.warning","data":.*"message":"Warning Message"/');
        $this->testSubscriber->notify($warningTriggered);
    }

    public function testNotifyHandlesDeprecationTriggeredEvent(): void
    {
        $test = $this->createTest('TestName');
        $deprecationTriggered = new DeprecationTriggered($this->createTelemetryInfo(), $test, 'Deprecation Message', 'file.php', 1, false, false, false, TestTrigger::self(), '');

        $this->expectOutputRegex('/"event":"test\.deprecation","data":.*"message":"Deprecation Message"/');
        $this->testSubscriber->notify($deprecationTriggered);
    }

    public function testNotifyHandlesPhpDeprecationTriggeredEvent(): void
    {
        $test = $this->createTest('TestName');
        $phpDeprecationTriggered = new PhpDeprecationTriggered($this->createTelemetryInfo(), $test, 'PHP Deprecation Message', 'file.php', 1, false, false, false, TestTrigger::self());

        $this->expectOutputRegex('/"event":"test\.deprecation","data":.*"message":"PHP Deprecation Message"/');
        $this->testSubscriber->notify($phpDeprecationTriggered);
    }

    public function testNotifyHandlesPhpWarningTriggeredEvent(): void
    {
        $test = $this->createTest('TestName');
        $phpWarningTriggered = new PhpWarningTriggered($this->createTelemetryInfo(), $test, 'PHP Warning Message', 'file.php', 1, false, '');

        $this->expectOutputRegex('/"event":"test\.warning","data":.*"message":"PHP Warning Message"/');
        $this->testSubscriber->notify($phpWarningTriggered);
    }

    public function testNotifyHandlesTestSuiteStartedEvent(): void
    {
        $suiteMock = $this->createMock(TestSuite::class);
        $testCollectionMock = TestCollection::fromArray([$this->createTest('Test1'), $this->createTest('Test1')]);

        $suiteMock->method('isForTestClass')->willReturn(true);
        $suiteMock->method('name')->willReturn('SuiteName');
        $suiteMock->method('tests')->willReturn($testCollectionMock);

        $started = new TestSuiteStarted($this->createTelemetryInfo(), $suiteMock);

        $this->expectOutputRegex('/"event":"suite\.started","data":.*"name":"SuiteName".*"count":2/');
        $this->testSubscriber->notify($started);
    }

    public function testNotifyHandlesTestRunnerFinishedEvent(): void
    {
        $finished = new TestRunnerFinished($this->createTelemetryInfo());

        $this->expectOutputRegex('/"event":"execution\.ended","data":.*"summary"/');
        $this->testSubscriber->notify($finished);
    }

    public function testNotifyHandlesTestFinishedEvent(): void
    {
        $test = $this->createTest('TestName');
        $finished = new Finished($this->createTelemetryInfo(), $test, 3);

        $this->expectOutputRegex('/"event":"test\.finished","data":.*"assertions":3/');
        $this->testSubscriber->notify($finished);
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
        $systemStopWatch = new SystemStopWatch();
        $systemMemoryMeter = new SystemMemoryMeter();
        $php83GarbageCollectorStatusProvider = new Php83GarbageCollectorStatusProvider();
        $system = new System($systemStopWatch, $systemMemoryMeter, $php83GarbageCollectorStatusProvider);

        $hrTime = HRTime::fromSecondsAndNanoseconds(123, 456);
        $memoryUsage = MemoryUsage::fromBytes(1024);

        $snapshot = new Snapshot($hrTime, $memoryUsage, $memoryUsage, $php83GarbageCollectorStatusProvider->status());

        return new Info($system->snapshot(), $systemStopWatch->current()->duration($hrTime), $systemMemoryMeter->memoryUsage(), $snapshot->time()->duration($hrTime), $snapshot->memoryUsage());
    }

    private function createThrowableMock(string $message, string $trace = ''): Throwable
    {
        return new Throwable('', $message, '', $trace, null);
    }
}
