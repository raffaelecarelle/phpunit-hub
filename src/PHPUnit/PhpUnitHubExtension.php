<?php

declare(strict_types=1);

namespace PhpUnitHub\PHPUnit;

use PHPUnit\Event\Test\ConsideredRisky;
use PHPUnit\Event\Test\ConsideredRiskySubscriber;
use PHPUnit\Event\Test\NoticeTriggered;
use PHPUnit\Event\Test\NoticeTriggeredSubscriber;
use PHPUnit\Event\Test\PhpNoticeTriggered;
use PHPUnit\Event\Test\PhpNoticeTriggeredSubscriber;
use PHPUnit\Event\Test\PreparedSubscriber;
use Closure;
use PHPUnit\Event\Test\PassedSubscriber;
use PHPUnit\Event\Test\FailedSubscriber;
use PHPUnit\Event\Test\ErroredSubscriber;
use PHPUnit\Event\Test\SkippedSubscriber;
use PHPUnit\Event\Test\MarkedIncompleteSubscriber;
use PHPUnit\Event\Test\WarningTriggeredSubscriber;
use PHPUnit\Event\Test\DeprecationTriggeredSubscriber;
use PHPUnit\Event\Test\PhpDeprecationTriggeredSubscriber;
use PHPUnit\Event\Test\PhpWarningTriggeredSubscriber;
use PHPUnit\Event\TestSuite\StartedSubscriber;
use PHPUnit\Event\TestRunner\FinishedSubscriber;
use PHPUnit\Event\Test\DeprecationTriggered;
use PHPUnit\Event\Test\Finished;
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
use PHPUnit\Event\Test\FinishedSubscriber as TestFinishedSubscriber;

class PhpUnitHubExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        // Helper function to write events to STDERR (to avoid mixing with PHPUnit's normal output)
        $writeEvent = static function (string $event, array $data): void {
            fwrite(STDERR, json_encode(['event' => $event, 'data' => $data]) . "\n");
        };

        $formatTestId = static function (string $testId): string {
            if (preg_match('/^(.*?) with data set "(.*)"$/', $testId, $matches)) {
                [, $methodName, $dataSetName] = $matches;
                // Attempt to decode the JSON from the data set name
                $dataSet = json_decode($dataSetName, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Re-serialize the data to a more readable, multi-line format
                    $formattedDataSet = json_encode($dataSet, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    return $methodName . PHP_EOL . $formattedDataSet;
                }

                // Fallback for non-JSON data sets
                return sprintf('%s with data set %s', $methodName, $dataSetName);
            }

            return $testId;
        };

        $facade->registerSubscriber(new class ($writeEvent, $formatTestId) implements PreparedSubscriber {
            private readonly Closure $writeEvent;

            private readonly Closure $formatTestId;

            public function __construct(callable $writeEvent, callable $formatTestId)
            {
                $this->writeEvent = $writeEvent(...);
                $this->formatTestId = $formatTestId(...);
            }

            public function notify(Prepared $event): void
            {
                ($this->writeEvent)('test.prepared', [
                    'testId' => $event->test()->id(),
                    'testName' => ($this->formatTestId)($event->test()->name()),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent, $formatTestId) implements PassedSubscriber {
            private readonly Closure $writeEvent;

            private readonly Closure $formatTestId;

            public function __construct(callable $writeEvent, callable $formatTestId)
            {
                $this->writeEvent = $writeEvent(...);
                $this->formatTestId = $formatTestId(...);
            }

            public function notify(Passed $event): void
            {
                ($this->writeEvent)('test.passed', [
                    'testId' => $event->test()->id(),
                    'testName' => ($this->formatTestId)($event->test()->name()),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent, $formatTestId) implements FailedSubscriber {
            private readonly Closure $writeEvent;

            private readonly Closure $formatTestId;

            public function __construct(callable $writeEvent, callable $formatTestId)
            {
                $this->writeEvent = $writeEvent(...);
                $this->formatTestId = $formatTestId(...);
            }

            public function notify(Failed $event): void
            {
                ($this->writeEvent)('test.failed', [
                    'testId' => $event->test()->id(),
                    'testName' => ($this->formatTestId)($event->test()->name()),
                    'message' => $event->throwable()->message(),
                    'trace' => $event->throwable()->stackTrace(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent, $formatTestId) implements ErroredSubscriber {
            private readonly Closure $writeEvent;

            private readonly Closure $formatTestId;

            public function __construct(callable $writeEvent, callable $formatTestId)
            {
                $this->writeEvent = $writeEvent(...);
                $this->formatTestId = $formatTestId(...);
            }

            public function notify(Errored $event): void
            {
                ($this->writeEvent)('test.errored', [
                    'testId' => $event->test()->id(),
                    'testName' => ($this->formatTestId)($event->test()->name()),
                    'message' => $event->throwable()->message(),
                    'trace' => $event->throwable()->stackTrace(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent, $formatTestId) implements SkippedSubscriber {
            private readonly Closure $writeEvent;

            private readonly Closure $formatTestId;

            public function __construct(callable $writeEvent, callable $formatTestId)
            {
                $this->writeEvent = $writeEvent(...);
                $this->formatTestId = $formatTestId(...);
            }

            public function notify(Skipped $event): void
            {
                ($this->writeEvent)('test.skipped', [
                    'testId' => $event->test()->id(),
                    'testName' => ($this->formatTestId)($event->test()->name()),
                    'message' => $event->message(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent, $formatTestId) implements MarkedIncompleteSubscriber {
            private readonly Closure $writeEvent;

            private readonly Closure $formatTestId;

            public function __construct(callable $writeEvent, callable $formatTestId)
            {
                $this->writeEvent = $writeEvent(...);
                $this->formatTestId = $formatTestId(...);
            }

            public function notify(MarkedIncomplete $event): void
            {
                ($this->writeEvent)('test.incomplete', [
                    'testId' => $event->test()->id(),
                    'testName' => ($this->formatTestId)($event->test()->name()),
                    'message' => $event->throwable()->message(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent, $formatTestId) implements WarningTriggeredSubscriber {
            private readonly Closure $writeEvent;

            private readonly Closure $formatTestId;

            public function __construct(callable $writeEvent, callable $formatTestId)
            {
                $this->writeEvent = $writeEvent(...);
                $this->formatTestId = $formatTestId(...);
            }

            public function notify(WarningTriggered $event): void
            {
                ($this->writeEvent)('test.warning', [
                    'testId' => $event->test()->id(),
                    'testName' => ($this->formatTestId)($event->test()->name()),
                    'message' => $event->message(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent, $formatTestId) implements DeprecationTriggeredSubscriber {
            private readonly Closure $writeEvent;

            private readonly Closure $formatTestId;

            public function __construct(callable $writeEvent, callable $formatTestId)
            {
                $this->writeEvent = $writeEvent(...);
                $this->formatTestId = $formatTestId(...);
            }

            public function notify(DeprecationTriggered $event): void
            {
                ($this->writeEvent)('test.deprecation', [
                    'testId' => $event->test()->id(),
                    'testName' => ($this->formatTestId)($event->test()->name()),
                    'message' => $event->message(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent, $formatTestId) implements PhpDeprecationTriggeredSubscriber {
            private readonly Closure $writeEvent;

            private readonly Closure $formatTestId;

            public function __construct(callable $writeEvent, callable $formatTestId)
            {
                $this->writeEvent = $writeEvent(...);
                $this->formatTestId = $formatTestId(...);
            }

            public function notify(PhpDeprecationTriggered $event): void
            {
                ($this->writeEvent)('test.deprecation', [
                    'testId' => $event->test()->id(),
                    'testName' => ($this->formatTestId)($event->test()->name()),
                    'message' => $event->message(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent, $formatTestId) implements ConsideredRiskySubscriber {
            private readonly Closure $writeEvent;

            private readonly Closure $formatTestId;

            public function __construct(callable $writeEvent, callable $formatTestId)
            {
                $this->writeEvent = $writeEvent(...);
                $this->formatTestId = $formatTestId(...);
            }

            public function notify(ConsideredRisky $event): void
            {
                ($this->writeEvent)('test.risky', [
                    'testId' => $event->test()->id(),
                    'testName' => ($this->formatTestId)($event->test()->name()),
                    'message' => $event->message(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent, $formatTestId) implements NoticeTriggeredSubscriber {
            private readonly Closure $writeEvent;

            private readonly Closure $formatTestId;

            public function __construct(callable $writeEvent, callable $formatTestId)
            {
                $this->writeEvent = $writeEvent(...);
                $this->formatTestId = $formatTestId(...);
            }

            public function notify(NoticeTriggered $event): void
            {
                ($this->writeEvent)('test.notice', [
                    'testId' => $event->test()->id(),
                    'testName' => ($this->formatTestId)($event->test()->name()),
                    'message' => $event->message(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent, $formatTestId) implements PhpNoticeTriggeredSubscriber {
            private readonly Closure $writeEvent;

            private readonly Closure $formatTestId;

            public function __construct(callable $writeEvent, callable $formatTestId)
            {
                $this->writeEvent = $writeEvent(...);
                $this->formatTestId = $formatTestId(...);
            }

            public function notify(PhpNoticeTriggered $event): void
            {
                ($this->writeEvent)('test.notice', [
                    'testId' => $event->test()->id(),
                    'testName' => ($this->formatTestId)($event->test()->name()),
                    'message' => $event->message(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent, $formatTestId) implements PhpWarningTriggeredSubscriber {
            private readonly Closure $writeEvent;

            private readonly Closure $formatTestId;

            public function __construct(callable $writeEvent, callable $formatTestId)
            {
                $this->writeEvent = $writeEvent(...);
                $this->formatTestId = $formatTestId(...);
            }

            public function notify(PhpWarningTriggered $event): void
            {
                ($this->writeEvent)('test.warning', [
                    'testId' => $event->test()->id(),
                    'testName' => ($this->formatTestId)($event->test()->name()),
                    'message' => $event->message(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent) implements StartedSubscriber {
            private readonly Closure $writeEvent;

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

        $facade->registerSubscriber(new class ($writeEvent) implements FinishedSubscriber {
            private readonly Closure $writeEvent;

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
                        'numberOfNotices' => $testResult->numberOfNotices(),
                        'numberOfSkipped' => $testResult->numberOfTestSkippedEvents(),
                        'numberOfIncomplete' => $testResult->numberOfTestMarkedIncompleteEvents(),
                        'numberOfRisky' => $testResult->numberOfTestsWithTestConsideredRiskyEvents(),
                        'numberOfDeprecations' => $testResult->numberOfPhpOrUserDeprecations(),
                        'duration' => $event->telemetryInfo()->durationSinceStart()->nanoseconds(),
                    ],
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent, $formatTestId) implements TestFinishedSubscriber {
            private readonly Closure $writeEvent;

            private readonly Closure $formatTestId;

            public function __construct(callable $writeEvent, callable $formatTestId)
            {
                $this->writeEvent = $writeEvent(...);
                $this->formatTestId = $formatTestId(...);
            }

            public function notify(Finished $event): void
            {
                ($this->writeEvent)('test.finished', [
                    'testId' => $event->test()->id(),
                    'testName' => ($this->formatTestId)($event->test()->name()),
                    'duration' => $event->telemetryInfo()->durationSinceStart()->nanoseconds(),
                    'assertions' => $event->numberOfAssertionsPerformed(),
                ]);
            }
        });
    }
}
