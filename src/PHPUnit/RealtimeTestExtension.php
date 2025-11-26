<?php

declare(strict_types=1);

namespace PhpUnitHub\PHPUnit;

use PHPUnit\Event\Test\DeprecationTriggered;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\MarkedIncomplete;
use PHPUnit\Event\Test\Passed;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\WarningTriggered;
use PHPUnit\Event\TestRunner\Finished as TestRunnerFinished;
use PHPUnit\Event\TestSuite\Finished as TestSuiteFinished;
use PHPUnit\Event\TestSuite\Started as TestSuiteStarted;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use PHPUnit\TestRunner\TestResult\Facade as TestResultFacade;

class RealtimeTestExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $outputFile = $parameters->get('outputFile');

        // Helper function to write events to the file
        $writeEvent = static function (string $event, array $data) use ($outputFile): void {
            file_put_contents($outputFile, json_encode(['event' => $event, 'data' => $data]) . "\n", FILE_APPEND);
        };

        // Register individual subscribers for each event type
        $facade->registerSubscriber(new class($writeEvent) implements \PHPUnit\Event\Test\PreparedSubscriber {
            private $writeEvent;

            public function __construct(callable $writeEvent) { $this->writeEvent = $writeEvent; }
            public function notify(Prepared $event): void {
                ($this->writeEvent)('test.prepared', ['test' => $event->test()->id()]);
            }
        });

        $facade->registerSubscriber(new class($writeEvent) implements \PHPUnit\Event\Test\PassedSubscriber {
            private $writeEvent;

            public function __construct(callable $writeEvent) { $this->writeEvent = $writeEvent; }
            public function notify(Passed $event): void {
                ($this->writeEvent)('test.passed', ['test' => $event->test()->id(), 'time' => $event->telemetryInfo()->durationSinceStart()->asFloat()]);
            }
        });

        $facade->registerSubscriber(new class($writeEvent) implements \PHPUnit\Event\Test\FailedSubscriber {
            private $writeEvent;

            public function __construct(callable $writeEvent) { $this->writeEvent = $writeEvent; }
            public function notify(Failed $event): void {
                ($this->writeEvent)('test.failed', [
                    'test' => $event->test()->id(),
                    'message' => $event->throwable()->message(),
                    'trace' => $event->throwable()->stackTrace(),
                    'time' => $event->telemetryInfo()->durationSinceStart()->asFloat(),
                ]);
            }
        });

        $facade->registerSubscriber(new class($writeEvent) implements \PHPUnit\Event\Test\ErroredSubscriber {
            private $writeEvent;

            public function __construct(callable $writeEvent) { $this->writeEvent = $writeEvent; }
            public function notify(Errored $event): void {
                ($this->writeEvent)('test.errored', [
                    'test' => $event->test()->id(),
                    'message' => $event->throwable()->message(),
                    'trace' => $event->throwable()->stackTrace(),
                    'time' => $event->telemetryInfo()->durationSinceStart()->asFloat(),
                ]);
            }
        });

        $facade->registerSubscriber(new class($writeEvent) implements \PHPUnit\Event\Test\SkippedSubscriber {
            private $writeEvent;

            public function __construct(callable $writeEvent) { $this->writeEvent = $writeEvent; }
            public function notify(Skipped $event): void {
                ($this->writeEvent)('test.skipped', [
                    'test' => $event->test()->id(),
                    'message' => $event->message(),
                    'time' => $event->telemetryInfo()->durationSinceStart()->asFloat(),
                ]);
            }
        });

        $facade->registerSubscriber(new class($writeEvent) implements \PHPUnit\Event\Test\MarkedIncompleteSubscriber {
            private $writeEvent;

            public function __construct(callable $writeEvent) { $this->writeEvent = $writeEvent; }
            public function notify(MarkedIncomplete $event): void {
                ($this->writeEvent)('test.incomplete', [
                    'test' => $event->test()->id(),
                    'message' => $event->throwable()->message(),
                    'time' => $event->telemetryInfo()->durationSinceStart()->asFloat(),
                ]);
            }
        });

        $facade->registerSubscriber(new class($writeEvent) implements \PHPUnit\Event\Test\WarningTriggeredSubscriber {
            private $writeEvent;

            public function __construct(callable $writeEvent) { $this->writeEvent = $writeEvent; }
            public function notify(WarningTriggered $event): void {
                ($this->writeEvent)('test.warning', [
                    'test' => $event->test()->id(),
                    'message' => $event->message(),
                    'time' => $event->telemetryInfo()->durationSinceStart()->asFloat(),
                ]);
            }
        });

        $facade->registerSubscriber(new class($writeEvent) implements \PHPUnit\Event\Test\DeprecationTriggeredSubscriber {
            private $writeEvent;

            public function __construct(callable $writeEvent) { $this->writeEvent = $writeEvent; }
            public function notify(DeprecationTriggered $event): void {
                ($this->writeEvent)('test.deprecation', [], [
                    'test' => $event->test()->id(),
                    'message' => $event->message(),
                    'time' => $event->telemetryInfo()->durationSinceStart()->asFloat(),
                ]);
            }
        });

        $facade->registerSubscriber(new class($writeEvent) implements \PHPUnit\Event\TestSuite\StartedSubscriber {
            private $writeEvent;

            public function __construct(callable $writeEvent) { $this->writeEvent = $writeEvent; }
            public function notify(TestSuiteStarted $event): void {
                if ($event->testSuite()->isForTestClass()) {
                    ($this->writeEvent)('suite.started', [
                        'name' => $event->testSuite()->name(),
                        'count' => $event->testSuite()->tests()->count(),
                    ]);
                }
            }
        });

        $facade->registerSubscriber(new class($writeEvent) implements \PHPUnit\Event\TestSuite\FinishedSubscriber {
            private $writeEvent;

            public function __construct(callable $writeEvent) { $this->writeEvent = $writeEvent; }
            public function notify(TestSuiteFinished $event): void {
                if ($event->testSuite()->isForTestClass()) {
                    ($this->writeEvent)('suite.finished', [
                        'name' => $event->testSuite()->name(),
                        'numberOfTests' => $event->testSuite()->tests()->count(),
                        'numberOfTestsRun' => $event->testSuite()->numberOfTestsRun(),
                        'numberOfAssertions' => $event->testSuite()->numberOfAssertions(),
                        'numberOfErrors' => $event->testSuite()->numberOfErrors(),
                        'numberOfFailures' => $event->testSuite()->numberOfFailures(),
                        'numberOfWarnings' => $event->testSuite()->numberOfWarnings(),
                        'numberOfSkipped' => $event->testSuite()->numberOfSkipped(),
                        'numberOfIncomplete' => $event->testSuite()->numberOfIncomplete(),
                        'numberOfRisky' => $event->testSuite()->numberOfRisky(),
                        'duration' => $event->duration()->asFloat(),
                    ]);
                }
            }
        });

        $facade->registerSubscriber(new class($writeEvent) implements \PHPUnit\Event\TestRunner\FinishedSubscriber {
            private $writeEvent;

            public function __construct(callable $writeEvent) { $this->writeEvent = $writeEvent; }
            public function notify(TestRunnerFinished $event): void {

                $testResult = TestResultFacade::result();

                ($this->writeEvent)('execution.ended', [
                    'summary' => [
                        'numberOfTests' => $testResult->numberOfTestsRun(),
                        'numberOfAssertions' => $testResult->numberOfAssertions(),
                        'numberOfErrors' => $testResult->numberOfErrors(),
                        'numberOfFailures' => $testResult->numberOfTestFailedEvents(),
                        'numberOfWarnings' => $testResult->numberOfWarnings(),
                        'numberOfSkipped' => $testResult->numberOfTestSkippedEvents(),
                        'numberOfIncomplete' => $testResult->numberOfTestMarkedIncompleteEvents(),
                        'numberOfRisky' => $testResult->numberOfTestsWithTestConsideredRiskyEvents(),
                        'duration' => $event->telemetryInfo()->durationSinceStart()->asFloat(),
                    ]
                ]);
            }
        });
    }
}