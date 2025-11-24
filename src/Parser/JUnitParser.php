<?php

namespace PHPUnitGUI\Parser;

use Exception;
use SimpleXMLElement;

class JUnitParser
{
    /**
     * @throws Exception
     */
    public function parse(string $xmlContent): array
    {
        if (empty(trim($xmlContent))) {
            throw new Exception('Cannot parse empty XML content.');
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $errorMessages = array_map(fn($e) => trim($e->message), $errors);
            throw new Exception('Failed to parse XML: ' . implode(', ', $errorMessages));
        }

        $results = ['suites' => []];

        // Find all <testsuite> elements that contain <testcase> elements, regardless of nesting level.
        // This is a more robust way to get to the actual test suites.
        $testSuitesWithCases = $xml->xpath('//testsuite[testcase]');

        foreach ($testSuitesWithCases as $element) {
            $suiteData = [
                'name' => (string) $element['name'],
                'tests' => (int) $element['tests'],
                'assertions' => (int) $element['assertions'],
                'failures' => (int) $element['failures'],
                'errors' => (int) $element['errors'],
                'time' => (float) $element['time'],
                'testcases' => [],
            ];

            foreach ($element->testcase as $testcase) {
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

                if (isset($testcase->failure)) {
                    $caseData['status'] = 'failed';
                    $caseData['failure'] = [
                        'type' => (string) $testcase->failure['type'],
                        'message' => trim((string) $testcase->failure),
                    ];
                }

                if (isset($testcase->error)) {
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
