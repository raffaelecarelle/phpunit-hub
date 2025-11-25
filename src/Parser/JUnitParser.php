<?php

namespace PHPUnitGUI\Parser;

use Exception;
use SimpleXMLElement;

class JUnitParser
{
    /**
     * @return array{
     *     suites: array<array{
     *         name: string,
     *         tests: int,
     *         assertions: int,
     *         failures: int,
     *         errors: int,
     *         time: float,
     *         testcases: array<array{
     *             name: string,
     *             class: string,
     *             file: string,
     *             line: int,
     *             assertions: int,
     *             time: float,
     *             status: string,
     *             failure: ?array{type: string, message: string},
     *             error: ?array{type: string, message: string}
     *         }>
     *     }>,
     *     summary: array{
     *         tests: int,
     *         assertions: int,
     *         failures: int,
     *         errors: int,
     *         time: float
     *     }
     * }
     * @throws Exception
     */
    public function parse(string $xmlContent): array
    {
        if (in_array(trim($xmlContent), ['', '0'], true)) {
            throw new Exception('Cannot parse empty XML content.');
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $errorMessages = array_map(fn ($e) => trim($e->message), $errors);
            throw new Exception('Failed to parse XML: ' . implode(', ', $errorMessages));
        }

        $results = ['suites' => []];

        // Find all <testsuite> elements that contain <testcase> elements, regardless of nesting level.
        // This is a more robust way to get to the actual test suites.
        $testSuitesWithCases = $xml->xpath('//testsuite[testcase]');

        if (!is_array($testSuitesWithCases)) { // Changed from === false
            $testSuitesWithCases = [];
        }

        foreach ($testSuitesWithCases as $testSuiteWithCase) {
            $suiteData = [
                'name' => (string) $testSuiteWithCase['name'],
                'tests' => (int) $testSuiteWithCase['tests'],
                'assertions' => (int) $testSuiteWithCase['assertions'],
                'failures' => (int) $testSuiteWithCase['failures'],
                'errors' => (int) $testSuiteWithCase['errors'],
                'time' => (float) $testSuiteWithCase['time'],
                'testcases' => [],
            ];

            foreach ($testSuiteWithCase->testcase as $testcase) {
                $caseData = [
                    'name' => (string) $testcase['name'],
                    'class' => (string) $testcase['class'],
                    'file' => (string) $testcase['file'],
                    'line' => (int) $testcase['line'],
                    'assertions' => (int) $testcase['assertions'],
                    'time' => (float) $testcase['time'],
                    'status' => 'passed',
                    'failure' => null,
                    'error' => null,
                ];

                if (property_exists($testcase, 'failure') && $testcase->failure !== null) {
                    $caseData['status'] = 'failed';
                    $caseData['failure'] = [
                        'type' => (string) $testcase->failure['type'],
                        'message' => trim((string) $testcase->failure),
                    ];
                }

                if (property_exists($testcase, 'error') && $testcase->error !== null) {
                    $caseData['status'] = 'error';
                    $caseData['error'] = [
                        'type' => (string) $testcase->error['type'],
                        'message' => trim((string) $testcase->error),
                    ];
                }

                $suiteData['testcases'][] = $caseData;
            }

            $results['suites'][] = $suiteData;
        }

        // Calculate the summary based *only* on the suites we actually found and parsed.
        $summary = [
            'tests' => array_sum(array_column($results['suites'], 'tests')),
            'assertions' => array_sum(array_column($results['suites'], 'assertions')),
            'failures' => array_sum(array_column($results['suites'], 'failures')),
            'errors' => array_sum(array_column($results['suites'], 'errors')),
            'time' => array_sum(array_column($results['suites'], 'time')),
        ];

        $results['summary'] = $summary;

        return $results;
    }
}
