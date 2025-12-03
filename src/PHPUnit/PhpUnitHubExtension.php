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
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameterCollection): void
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

        // Register individual subscribers for each event type
        $facade->registerSubscriber(new class ($writeEvent, $formatTestId) implements PreparedSubscriber {
            private readonly Closure $writeEvent;

            private readonly Closure $formatTestId;

            public function __construct(callable $writeEvent, callable $formatTestId)
            {
                $this->writeEvent = $writeEvent(...);
                $this->formatTestId = $formatTestId(...);
            }

            public function notify(Prepared $prepared): void
            {
                ($this->writeEvent)('test.prepared', [
                    'testId' => $prepared->test()->id(),
                    'testName' => ($this->formatTestId)($prepared->test()->name()),
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

            public function notify(Passed $passed): void
            {
                ($this->writeEvent)('test.passed', [
                    'testId' => $passed->test()->id(),
                    'testName' => ($this->formatTestId)($passed->test()->name()),
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

            public function notify(Failed $failed): void
            {
                ($this->writeEvent)('test.failed', [
                    'testId' => $failed->test()->id(),
                    'testName' => ($this->formatTestId)($failed->test()->name()),
                    'message' => $failed->throwable()->message(),
                    'trace' => $failed->throwable()->stackTrace(),
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

            public function notify(Errored $errored): void
            {
                ($this->writeEvent)('test.errored', [
                    'testId' => $errored->test()->id(),
                    'testName' => ($this->formatTestId)($errored->test()->name()),
                    'message' => $errored->throwable()->message(),
                    'trace' => $errored->throwable()->stackTrace(),
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

            public function notify(Skipped $skipped): void
            {
                ($this->writeEvent)('test.skipped', [
                    'testId' => $skipped->test()->id(),
                    'testName' => ($this->formatTestId)($skipped->test()->name()),
                    'message' => $skipped->message(),
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

            public function notify(MarkedIncomplete $markedIncomplete): void
            {
                ($this->writeEvent)('test.incomplete', [
                    'testId' => $markedIncomplete->test()->id(),
                    'testName' => ($this->formatTestId)($markedIncomplete->test()->name()),
                    'message' => $markedIncomplete->throwable()->message(),
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

            public function notify(WarningTriggered $warningTriggered): void
            {
                ($this->writeEvent)('test.warning', [
                    'testId' => $warningTriggered->test()->id(),
                    'testName' => ($this->formatTestId)($warningTriggered->test()->name()),
                    'message' => $warningTriggered->message(),
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

            public function notify(DeprecationTriggered $deprecationTriggered): void
            {
                ($this->writeEvent)('test.deprecation', [
                    'testId' => $deprecationTriggered->test()->id(),
                    'testName' => ($this->formatTestId)($deprecationTriggered->test()->name()),
                    'message' => $deprecationTriggered->message(),
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

            public function notify(PhpDeprecationTriggered $phpDeprecationTriggered): void
            {
                ($this->writeEvent)('test.deprecation', [
                    'testId' => $phpDeprecationTriggered->test()->id(),
                    'testName' => ($this->formatTestId)($phpDeprecationTriggered->test()->name()),
                    'message' => $phpDeprecationTriggered->message(),
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

            public function notify(ConsideredRisky $consideredRisky): void
            {
                ($this->writeEvent)('test.risky', [
                    'testId' => $consideredRisky->test()->id(),
                    'testName' => ($this->formatTestId)($consideredRisky->test()->name()),
                    'message' => $consideredRisky->message(),
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

            public function notify(NoticeTriggered $noticeTriggered): void
            {
                ($this->writeEvent)('test.notice', [
                    'testId' => $noticeTriggered->test()->id(),
                    'testName' => ($this->formatTestId)($noticeTriggered->test()->name()),
                    'message' => $noticeTriggered->message(),
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

            public function notify(PhpNoticeTriggered $phpNoticeTriggered): void
            {
                ($this->writeEvent)('test.notice', [
                    'testId' => $phpNoticeTriggered->test()->id(),
                    'testName' => ($this->formatTestId)($phpNoticeTriggered->test()->name()),
                    'message' => $phpNoticeTriggered->message(),
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

            public function notify(PhpWarningTriggered $phpWarningTriggered): void
            {
                ($this->writeEvent)('test.warning', [
                    'testId' => $phpWarningTriggered->test()->id(),
                    'testName' => ($this->formatTestId)($phpWarningTriggered->test()->name()),
                    'message' => $phpWarningTriggered->message(),
                ]);
            }
        });

        $facade->registerSubscriber(new class ($writeEvent) implements StartedSubscriber {
            private readonly Closure $writeEvent;

            public function __construct(callable $writeEvent)
            {
                $this->writeEvent = $writeEvent(...);
            }

            public function notify(TestSuiteStarted $testSuiteStarted): void
            {
                if ($testSuiteStarted->testSuite()->isForTestClass()) {
                    ($this->writeEvent)('suite.started', [
                        'name' => $testSuiteStarted->testSuite()->name(),
                        'count' => $testSuiteStarted->testSuite()->tests()->count(),
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

            public function notify(TestRunnerFinished $testRunnerFinished): void
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
                        'duration' => $testRunnerFinished->telemetryInfo()->durationSinceStart()->nanoseconds(),
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

            public function notify(Finished $finished): void
            {
                ($this->writeEvent)('test.finished', [
                    'testId' => $finished->test()->id(),
                    'testName' => ($this->formatTestId)($finished->test()->name()),
                    'duration' => $finished->telemetryInfo()->durationSinceStart()->nanoseconds(),
                    'assertions' => $finished->numberOfAssertionsPerformed(),
                ]);
            }
        });
    }
}
