<?php

declare(strict_types=1);

namespace PhpUnitHub\PHPUnit;

use PHPUnit\Event\Test\DeprecationTriggered;
use PHPUnit\Event\Test\PhpDeprecationTriggered;
use PHPUnit\Event\Test\PhpWarningTriggered;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\MarkedIncomplete;
use PHPUnit\Event\Test\Passed;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\WarningTriggered;
use PHPUnit\Event\TestRunner\Finished as TestRunnerFinished;
use PHPUnit\Event\TestSuite\Started as TestSuiteStarted;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use PHPUnit\TestRunner\TestResult\Facade as TestResultFacade;

class PhpUnitHubExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        // Helper function to write events to STDERR (to avoid mixing with PHPUnit's normal output)
        $writeEvent = static function (string $event, array $data): void {
            fwrite(STDERR, json_encode(['event' => $event, 'data' => $data]) . "\n");
        };

        // Register individual subscribers for each event type
        $facade->registerSubscriber(new class ($writeEvent) implements \PHPUnit\Event\Test\PreparedSubscriber {
            private readonly \Closure $writeEvent;

            public function __construct(callable $writeEvent)
            {
                $this->writeEvent = $writeEvent(...);
            }

            public function notify(Prepared $event): void
            {
                ($this->writeEvent)('test.prepared', ['test' => $event->test()->id()]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent) implements \PHPUnit\Event\Test\PassedSubscriber {
            private readonly \Closure $writeEvent;

            public function __construct(callable $writeEvent)
            {
                $this->writeEvent = $writeEvent(...);
            }

            public function notify(Passed $event): void
            {
                ($this->writeEvent)('test.passed', ['test' => $event->test()->id(), 'time' => $event->telemetryInfo()->durationSinceStart()->asFloat()]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent) implements \PHPUnit\Event\Test\FailedSubscriber {
            private readonly \Closure $writeEvent;

            public function __construct(callable $writeEvent)
            {
                $this->writeEvent = $writeEvent(...);
            }

            public function notify(Failed $event): void
            {
                ($this->writeEvent)('test.failed', [
                    'test' => $event->test()->id(),
                    'message' => $event->throwable()->message(),
                    'trace' => $event->throwable()->stackTrace(),
                    'time' => $event->telemetryInfo()->durationSinceStart()->asFloat(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent) implements \PHPUnit\Event\Test\ErroredSubscriber {
            private readonly \Closure $writeEvent;

            public function __construct(callable $writeEvent)
            {
                $this->writeEvent = $writeEvent(...);
            }

            public function notify(Errored $event): void
            {
                ($this->writeEvent)('test.errored', [
                    'test' => $event->test()->id(),
                    'message' => $event->throwable()->message(),
                    'trace' => $event->throwable()->stackTrace(),
                    'time' => $event->telemetryInfo()->durationSinceStart()->asFloat(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent) implements \PHPUnit\Event\Test\SkippedSubscriber {
            private readonly \Closure $writeEvent;

            public function __construct(callable $writeEvent)
            {
                $this->writeEvent = $writeEvent(...);
            }

            public function notify(Skipped $event): void
            {
                ($this->writeEvent)('test.skipped', [
                    'test' => $event->test()->id(),
                    'message' => $event->message(),
                    'time' => $event->telemetryInfo()->durationSinceStart()->asFloat(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent) implements \PHPUnit\Event\Test\MarkedIncompleteSubscriber {
            private readonly \Closure $writeEvent;

            public function __construct(callable $writeEvent)
            {
                $this->writeEvent = $writeEvent(...);
            }

            public function notify(MarkedIncomplete $event): void
            {
                ($this->writeEvent)('test.incomplete', [
                    'test' => $event->test()->id(),
                    'message' => $event->throwable()->message(),
                    'time' => $event->telemetryInfo()->durationSinceStart()->asFloat(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent) implements \PHPUnit\Event\Test\WarningTriggeredSubscriber {
            private readonly \Closure $writeEvent;

            public function __construct(callable $writeEvent)
            {
                $this->writeEvent = $writeEvent(...);
            }

            public function notify(WarningTriggered $event): void
            {
                ($this->writeEvent)('test.warning', [
                    'test' => $event->test()->id(),
                    'message' => $event->message(),
                    'time' => $event->telemetryInfo()->durationSinceStart()->asFloat(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent) implements \PHPUnit\Event\Test\DeprecationTriggeredSubscriber {
            private readonly \Closure $writeEvent;

            public function __construct(callable $writeEvent)
            {
                $this->writeEvent = $writeEvent(...);
            }

            public function notify(DeprecationTriggered $event): void
            {
                ($this->writeEvent)('test.deprecation', [
                    'test' => $event->test()->id(),
                    'message' => $event->message(),
                    'time' => $event->telemetryInfo()->durationSinceStart()->asFloat(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent) implements \PHPUnit\Event\Test\PhpDeprecationTriggeredSubscriber {
            private readonly \Closure $writeEvent;

            public function __construct(callable $writeEvent)
            {
                $this->writeEvent = $writeEvent(...);
            }

            public function notify(PhpDeprecationTriggered $event): void
            {
                ($this->writeEvent)('test.deprecation', [
                    'test' => $event->test()->id(),
                    'message' => $event->message(),
                    'time' => $event->telemetryInfo()->durationSinceStart()->asFloat(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent) implements \PHPUnit\Event\Test\PhpWarningTriggeredSubscriber {
            private readonly \Closure $writeEvent;

            public function __construct(callable $writeEvent)
            {
                $this->writeEvent = $writeEvent(...);
            }

            public function notify(PhpWarningTriggered $event): void
            {
                ($this->writeEvent)('test.warning', [
                    'test' => $event->test()->id(),
                    'message' => $event->message(),
                    'time' => $event->telemetryInfo()->durationSinceStart()->asFloat(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent) implements \PHPUnit\Event\TestSuite\StartedSubscriber {
            private readonly \Closure $writeEvent;

            public function __construct(callable $writeEvent)
            {
                $this->writeEvent = $writeEvent(...);
            }

            public function notify(TestSuiteStarted $event): void
            {
                if ($event->testSuite()->isForTestClass()) {
                    ($this->writeEvent)('suite.started', [
                        'name' => $event->testSuite()->name(),
                        'count' => $event->testSuite()->tests()->count(),
                    ]);
                }
            }
        });

        $facade->registerSubscriber(new class ($writeEvent) implements \PHPUnit\Event\TestRunner\FinishedSubscriber {
            private readonly \Closure $writeEvent;

            public function __construct(callable $writeEvent)
            {
                $this->writeEvent = $writeEvent(...);
            }

            public function notify(TestRunnerFinished $event): void
            {
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
                        'numberOfDeprecations' => $testResult->numberOfPhpOrUserDeprecations(),
                        'duration' => $event->telemetryInfo()->durationSinceStart()->asFloat(),
                    ],
                ]);
            }
        });
    }
}
