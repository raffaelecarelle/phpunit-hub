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

use function getenv;
use function stream_socket_client;

/**
 * PhpUnitHubExtension is a PHPUnit extension that captures test execution events
 * and reports them to an external GUI. It's designed to provide real-time feedback
 * on test progress and results.
 */
class PhpUnitHubExtension implements Extension
{
    /**
     * This is the entry point for the extension. It's called by PHPUnit during its bootstrap phase.
     * It sets up all the necessary subscribers to listen for test events.
     */
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        // When running tests in parallel (e.g., with ParaTest), standard output can be buffered,
        // which delays the GUI from receiving event data. To solve this, we open a direct TCP socket
        // to the GUI if the 'PHPUNIT_GUI_TCP_PORT' environment variable is set.
        $socket = null;
        $tcpPort = getenv('PHPUNIT_GUI_TCP_PORT');
        if ($tcpPort !== false) {
            // The '@' suppresses errors in case the connection fails (e.g., if the GUI is not running).
            // We use a persistent connection to avoid reconnecting for every event within the same worker process.
            $socket = @stream_socket_client(
                'tcp://127.0.0.1:' . $tcpPort,
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT
            );
        }

        // This helper function is the central communication channel to the GUI.
        // It serializes event data into a JSON payload and sends it.
        $writeEvent = static function (string $event, array $data) use ($socket): void {
            // We append a newline character to each payload. This is crucial for message framing
            // on the server side, allowing it to distinguish between separate JSON messages in the TCP stream.
            $payload = json_encode(['event' => $event, 'data' => $data]) . "\n";

            if ($socket !== null) {
                // If the TCP socket is available, send the data over it.
                // This is the preferred method for real-time, unbuffered communication.
                @fwrite($socket, $payload);
                return;
            }

            // As a fallback, if the socket is not available, we write the event data to STDERR.
            // We use STDERR to avoid mixing our JSON output with PHPUnit's standard test results on STDOUT.
            fwrite(STDERR, $payload);
        };

        // This helper function formats the test ID for better readability in the GUI.
        // Specifically, it handles tests that use data providers.
        $formatTestId = static function (string $testId): string {
            // Check if the test name matches the pattern for a test with a data set.
            if (preg_match('/^(.*?) with data set "(.*)"$/', $testId, $matches)) {
                [, $methodName, $dataSetName] = $matches;

                // Attempt to decode the data set name as a JSON string.
                // Data providers often serialize complex data types (like arrays or objects) as JSON.
                $dataSet = json_decode($dataSetName, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // If the JSON is valid, re-serialize it in a pretty-printed, multi-line format.
                    // This makes complex data sets much easier to read in the GUI.
                    $formattedDataSet = json_encode($dataSet, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    return $methodName . PHP_EOL . $formattedDataSet;
                }

                // If the data set is not a valid JSON string, fall back to the default format.
                return sprintf('%s with data set %s', $methodName, $dataSetName);
            }

            // If the test name doesn't match the data set pattern, return it as is.
            return $testId;
        };

        // The following section registers subscribers for various PHPUnit events.
        // Each subscriber is an anonymous class that captures specific event data
        // and sends it to the GUI using the $writeEvent helper.

        // Subscriber for when a test is about to start.
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

        // Subscriber for when a test passes successfully.
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

        // Subscriber for when a test fails due to an assertion failure.
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

        // Subscriber for when a test encounters an error (e.g., an uncaught exception).
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

        // Subscriber for when a test is skipped (e.g., via markTestSkipped()).
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

        // Subscriber for when a test is marked as incomplete.
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

        // Subscriber for when a test triggers a warning.
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

        // Subscriber for when a test triggers a user-level deprecation notice.
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

        // Subscriber for when a test triggers a PHP-level deprecation notice.
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

        // Subscriber for when a test is considered risky (e.g., performs no assertions).
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

        // Subscriber for when a test triggers a user-level notice.
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

        // Subscriber for when a test triggers a PHP-level notice.
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

        // Subscriber for when a test triggers a PHP-level warning.
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

        // Subscriber for when a test suite (a class of tests) starts.
        $facade->registerSubscriber(new class ($writeEvent) implements StartedSubscriber {
            private readonly Closure $writeEvent;

            public function __construct(callable $writeEvent)
            {
                $this->writeEvent = $writeEvent(...);
            }

            public function notify(TestSuiteStarted $event): void
            {
                // We only care about suites that represent a test class, not other groupings.
                if ($event->testSuite()->isForTestClass()) {
                    ($this->writeEvent)('suite.started', [
                        'name' => $event->testSuite()->name(),
                        'count' => $event->testSuite()->tests()->count(),
                    ]);
                }
            }
        });

        // Subscriber for when the entire test runner has finished its execution.
        $facade->registerSubscriber(new class ($writeEvent) implements FinishedSubscriber {
            private readonly Closure $writeEvent;

            public function __construct(callable $writeEvent)
            {
                $this->writeEvent = $writeEvent(...);
            }

            public function notify(TestRunnerFinished $event): void
            {
                if (getenv('TEST_TOKEN') !== false) {  // Using ParaTest
                    return;
                }

                // After the run is finished, we collect the final statistics.
                $testResult = TestResultFacade::result();

                // Send a final summary event with all the collected statistics.
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

        // Subscriber for when an individual test has finished, regardless of its outcome.
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
                // This event provides per-test metrics like duration and assertion count.
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
