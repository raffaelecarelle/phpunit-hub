<?php

declare(strict_types=1);

namespace PhpUnitHub\PHPUnit;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use Throwable;

use function fflush;
use function fwrite;
use function json_decode;
use function json_encode;
use function json_last_error;
use function preg_match;
use function sprintf;

class PhpUnitHubExtension9 implements TestListener
{
    use TestListenerDefaultImplementation;

    private array $testStatus = [];

    private function writeEvent(string $event, array $data): void
    {
        fwrite(STDERR, json_encode(['event' => $event, 'data' => $data]) . "\n");
        fflush(STDERR);
    }

    private function formatTestName(Test $test): string
    {
        if (!$test instanceof TestCase) {
            return $test::class;
        }

        $nameWithDataSet = $test->getName();

        if (preg_match('/^(.*?) with data set "(.*)"$/s', $nameWithDataSet, $matches)) {
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

        return $test->getName();
    }

    private function getTestId(Test $test): string
    {
        if ($test instanceof TestCase) {
            return $test::class . '::' . $test->getName(true);
        }

        return $test::class;
    }

    public function startTestSuite(TestSuite $testSuite): void
    {
        $this->writeEvent('suite.started', [
            'name' => $testSuite->getName(),
            'count' => $testSuite->count(),
        ]);
    }

    public function endTestSuite(TestSuite $testSuite): void
    {
        $this->writeEvent('suite.finished', [
            'name' => $testSuite->getName(),
        ]);
    }

    public function startTest(Test $test): void
    {
        $testId = $this->getTestId($test);
        $this->testStatus[$testId] = 'prepared';

        $this->writeEvent('test.prepared', [
            'testId' => $testId,
            'testName' => $this->formatTestName($test),
        ]);
    }

    public function endTest(Test $test, float $time): void
    {
        $testId = $this->getTestId($test);
        $status = $this->testStatus[$testId] ?? 'unknown';

        if ($status === 'prepared') {
            $this->writeEvent('test.passed', [
                'testId' => $testId,
                'testName' => $this->formatTestName($test),
            ]);
        }

        $assertions = 0;
        if ($test instanceof TestCase) {
            $assertions = $test->getNumAssertions();
        }

        $this->writeEvent('test.finished', [
            'testId' => $testId,
            'testName' => $this->formatTestName($test),
            'duration' => $time * 1e9,
            'assertions' => $assertions,
        ]);

        unset($this->testStatus[$testId]);
    }

    public function addError(Test $test, Throwable $throwable, float $time): void
    {
        $testId = $this->getTestId($test);
        $this->testStatus[$testId] = 'errored';
        $this->writeEvent('test.errored', [
            'testId' => $testId,
            'testName' => $this->formatTestName($test),
            'message' => $throwable->getMessage(),
            'trace' => $throwable->getTraceAsString(),
        ]);
    }

    public function addWarning(Test $test, Warning $warning, float $time): void
    {
        $testId = $this->getTestId($test);
        $this->testStatus[$testId] = 'warning';
        $this->writeEvent('test.warning', [
            'testId' => $testId,
            'testName' => $this->formatTestName($test),
            'message' => $warning->getMessage(),
        ]);
    }

    public function addFailure(Test $test, AssertionFailedError $assertionFailedError, float $time): void
    {
        $testId = $this->getTestId($test);
        $this->testStatus[$testId] = 'failed';
        $this->writeEvent('test.failed', [
            'testId' => $testId,
            'testName' => $this->formatTestName($test),
            'message' => $assertionFailedError->getMessage(),
            'trace' => $assertionFailedError->getTraceAsString(),
        ]);
    }

    public function addIncompleteTest(Test $test, Throwable $throwable, float $time): void
    {
        $testId = $this->getTestId($test);
        $this->testStatus[$testId] = 'incomplete';
        $this->writeEvent('test.incomplete', [
            'testId' => $testId,
            'testName' => $this->formatTestName($test),
            'message' => $throwable->getMessage(),
        ]);
    }

    public function addRiskyTest(Test $test, Throwable $throwable, float $time): void
    {
        $testId = $this->getTestId($test);
        $this->testStatus[$testId] = 'risky';
        $this->writeEvent('test.risky', [
            'testId' => $testId,
            'testName' => $this->formatTestName($test),
            'message' => $throwable->getMessage(),
        ]);
    }

    public function addSkippedTest(Test $test, Throwable $throwable, float $time): void
    {
        $testId = $this->getTestId($test);
        $this->testStatus[$testId] = 'skipped';
        $this->writeEvent('test.skipped', [
            'testId' => $testId,
            'testName' => $this->formatTestName($test),
            'message' => $throwable->getMessage(),
        ]);
    }
}
