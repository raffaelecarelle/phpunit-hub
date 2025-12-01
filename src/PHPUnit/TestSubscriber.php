<?php

declare(strict_types=1);

namespace PhpUnitHub\PHPUnit;

use Closure;
use PHPUnit\Event\Event;
use PHPUnit\Event\Test\DeprecationTriggered;
use PHPUnit\Event\Test\DeprecationTriggeredSubscriber;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber as TestFinishedSubscriber;
use PHPUnit\Event\Test\MarkedIncomplete;
use PHPUnit\Event\Test\MarkedIncompleteSubscriber;
use PHPUnit\Event\Test\Passed;
use PHPUnit\Event\Test\PassedSubscriber;
use PHPUnit\Event\Test\PhpDeprecationTriggered;
use PHPUnit\Event\Test\PhpDeprecationTriggeredSubscriber;
use PHPUnit\Event\Test\PhpWarningTriggered;
use PHPUnit\Event\Test\PhpWarningTriggeredSubscriber;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\SkippedSubscriber;
use PHPUnit\Event\Test\WarningTriggered;
use PHPUnit\Event\Test\WarningTriggeredSubscriber;
use PHPUnit\Event\TestRunner\Finished as TestRunnerFinished;
use PHPUnit\Event\TestRunner\FinishedSubscriber as TestRunnerFinishedSubscriber;
use PHPUnit\Event\TestSuite\Started as TestSuiteStarted;
use PHPUnit\Event\TestSuite\StartedSubscriber as TestSuiteStartedSubscriber;
use PHPUnit\TestRunner\TestResult\Facade as TestResultFacade;

use function fwrite;
use function json_encode;

class TestSubscriber implements
    PreparedSubscriber,
    PassedSubscriber,
    FailedSubscriber,
    ErroredSubscriber,
    SkippedSubscriber,
    MarkedIncompleteSubscriber,
    WarningTriggeredSubscriber,
    DeprecationTriggeredSubscriber,
    PhpDeprecationTriggeredSubscriber,
    PhpWarningTriggeredSubscriber,
    TestSuiteStartedSubscriber,
    TestRunnerFinishedSubscriber,
    TestFinishedSubscriber
{
    private readonly Closure $writeEvent;

    private readonly Closure $formatTestId;

    public function __construct()
    {
        $this->writeEvent = static function (string $event, array $data): void {
            fwrite(STDERR, json_encode(['event' => $event, 'data' => $data]) . "\n");
        };

        $this->formatTestId = static function (string $testId): string {
            if (preg_match('/^(.*?) with data set "(.*)"$/', $testId, $matches)) {
                [, $methodName, $dataSetName] = $matches;
                $dataSet = json_decode($dataSetName, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $formattedDataSet = json_encode($dataSet, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    return $methodName . PHP_EOL . $formattedDataSet;
                }

                return sprintf('%s with data set %s', $methodName, $dataSetName);
            }

            return $testId;
        };
    }

    public function notify(Event $event): void
    {
        match (true) {
            $event instanceof Prepared => $this->handlePrepared($event),
            $event instanceof Passed => $this->handlePassed($event),
            $event instanceof Failed => $this->handleFailed($event),
            $event instanceof Errored => $this->handleErrored($event),
            $event instanceof Skipped => $this->handleSkipped($event),
            $event instanceof MarkedIncomplete => $this->handleMarkedIncomplete($event),
            $event instanceof WarningTriggered => $this->handleWarningTriggered($event),
            $event instanceof DeprecationTriggered => $this->handleDeprecationTriggered($event),
            $event instanceof PhpDeprecationTriggered => $this->handlePhpDeprecationTriggered($event),
            $event instanceof PhpWarningTriggered => $this->handlePhpWarningTriggered($event),
            $event instanceof TestSuiteStarted => $this->handleTestSuiteStarted($event),
            $event instanceof TestRunnerFinished => $this->handleTestRunnerFinished($event),
            $event instanceof Finished => $this->handleTestFinished($event),
            default => null,
        };
    }

    private function handlePrepared(Prepared $prepared): void
    {
        ($this->writeEvent)('test.prepared', ['test' => ($this->formatTestId)($prepared->test()->name())]);
    }

    private function handlePassed(Passed $passed): void
    {
        ($this->writeEvent)('test.passed', ['test' => ($this->formatTestId)($passed->test()->name())]);
    }

    private function handleFailed(Failed $failed): void
    {
        ($this->writeEvent)('test.failed', [
            'test' => ($this->formatTestId)($failed->test()->name()),
            'message' => $failed->throwable()->message(),
            'trace' => $failed->throwable()->stackTrace(),
        ]);
    }

    private function handleErrored(Errored $errored): void
    {
        ($this->writeEvent)('test.errored', [
            'test' => ($this->formatTestId)($errored->test()->name()),
            'message' => $errored->throwable()->message(),
            'trace' => $errored->throwable()->stackTrace(),
        ]);
    }

    private function handleSkipped(Skipped $skipped): void
    {
        ($this->writeEvent)('test.skipped', [
            'test' => ($this->formatTestId)($skipped->test()->name()),
            'message' => $skipped->message(),
        ]);
    }

    private function handleMarkedIncomplete(MarkedIncomplete $markedIncomplete): void
    {
        ($this->writeEvent)('test.incomplete', [
            'test' => ($this->formatTestId)($markedIncomplete->test()->name()),
            'message' => $markedIncomplete->throwable()->message(),
        ]);
    }

    private function handleWarningTriggered(WarningTriggered $warningTriggered): void
    {
        ($this->writeEvent)('test.warning', [
            'test' => ($this->formatTestId)($warningTriggered->test()->name()),
            'message' => $warningTriggered->message(),
        ]);
    }

    private function handleDeprecationTriggered(DeprecationTriggered $deprecationTriggered): void
    {
        ($this->writeEvent)('test.deprecation', [
            'test' => ($this->formatTestId)($deprecationTriggered->test()->name()),
            'message' => $deprecationTriggered->message(),
        ]);
    }

    private function handlePhpDeprecationTriggered(PhpDeprecationTriggered $phpDeprecationTriggered): void
    {
        ($this->writeEvent)('test.deprecation', [
            'test' => ($this->formatTestId)($phpDeprecationTriggered->test()->name()),
            'message' => $phpDeprecationTriggered->message(),
        ]);
    }

    private function handlePhpWarningTriggered(PhpWarningTriggered $phpWarningTriggered): void
    {
        ($this->writeEvent)('test.warning', [
            'test' => ($this->formatTestId)($phpWarningTriggered->test()->name()),
            'message' => $phpWarningTriggered->message(),
        ]);
    }

    private function handleTestSuiteStarted(TestSuiteStarted $testSuiteStarted): void
    {
        if ($testSuiteStarted->testSuite()->isForTestClass()) {
            ($this->writeEvent)('suite.started', [
                'name' => $testSuiteStarted->testSuite()->name(),
                'count' => $testSuiteStarted->testSuite()->tests()->count(),
            ]);
        }
    }

    private function handleTestRunnerFinished(TestRunnerFinished $testRunnerFinished): void
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
                'duration' => $testRunnerFinished->telemetryInfo()->durationSinceStart()->nanoseconds(),
            ],
        ]);
    }

    private function handleTestFinished(Finished $finished): void
    {
        ($this->writeEvent)('test.finished', [
            'test' => ($this->formatTestId)($finished->test()->name()),
            'duration' => $finished->telemetryInfo()->durationSinceStart()->nanoseconds(),
            'assertions' => $finished->numberOfAssertionsPerformed(),
        ]);
    }
}
