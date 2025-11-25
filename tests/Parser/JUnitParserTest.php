<?php

namespace PHPUnitGUI\Tests\Parser;

use Exception;
use PHPUnit\Framework\TestCase;
use PHPUnitGUI\Parser\JUnitParser;

class JUnitParserTest extends TestCase
{
    private JUnitParser $jUnitParser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jUnitParser = new JUnitParser();
    }

    public function testParseWithAllPassedTests(): void
    {
        $xmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="MyTestSuite" tests="2" assertions="2" failures="0" errors="0" time="0.002">
                <testcase name="testAddition" class="MyNamespace\MyClassTest" file="/path/to/MyClassTest.php" line="10" assertions="1" time="0.001"/>
                <testcase name="testSubtraction" class="MyNamespace\MyClassTest" file="/path/to/MyClassTest.php" line="15" assertions="1" time="0.001"/>
              </testsuite>
              <testsuite name="AnotherTestSuite" tests="1" assertions="1" failures="0" errors="0" time="0.001">
                <testcase name="testSomethingElse" class="AnotherNamespace\AnotherClassTest" file="/path/to/AnotherClassTest.php" line="20" assertions="1" time="0.001"/>
              </testsuite>
            </testsuites>
            XML;

        $expected = [
            'suites' => [
                [
                    'name' => 'MyTestSuite',
                    'tests' => 2,
                    'assertions' => 2,
                    'failures' => 0,
                    'errors' => 0,
                    'time' => 0.002,
                    'testcases' => [
                        [
                            'name' => 'testAddition',
                            'class' => 'MyNamespace\MyClassTest',
                            'file' => '/path/to/MyClassTest.php',
                            'line' => 10,
                            'assertions' => 1,
                            'time' => 0.001,
                            'status' => 'passed',
                            'failure' => null,
                            'error' => null,
                        ],
                        [
                            'name' => 'testSubtraction',
                            'class' => 'MyNamespace\MyClassTest',
                            'file' => '/path/to/MyClassTest.php',
                            'line' => 15,
                            'assertions' => 1,
                            'time' => 0.001,
                            'status' => 'passed',
                            'failure' => null,
                            'error' => null,
                        ],
                    ],
                ],
                [
                    'name' => 'AnotherTestSuite',
                    'tests' => 1,
                    'assertions' => 1,
                    'failures' => 0,
                    'errors' => 0,
                    'time' => 0.001,
                    'testcases' => [
                        [
                            'name' => 'testSomethingElse',
                            'class' => 'AnotherNamespace\AnotherClassTest',
                            'file' => '/path/to/AnotherClassTest.php',
                            'line' => 20,
                            'assertions' => 1,
                            'time' => 0.001,
                            'status' => 'passed',
                            'failure' => null,
                            'error' => null,
                        ],
                    ],
                ],
            ],
            'summary' => [
                'tests' => 3,
                'assertions' => 3,
                'failures' => 0,
                'errors' => 0,
                'time' => 0.003,
            ],
        ];

        $this->assertEquals($expected, $this->jUnitParser->parse($xmlContent));
    }

    public function testParseWithFailedTests(): void
    {
        $xmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="FailedTestSuite" tests="1" assertions="1" failures="1" errors="0" time="0.001">
                <testcase name="testFailure" class="MyNamespace\FailedTest" file="/path/to/FailedTest.php" line="10" assertions="1" time="0.001">
                  <failure type="PHPUnit\Framework\ExpectationFailedException" message="Failed asserting that false is true.">
                    <![CDATA[/path/to/FailedTest.php:12]]>
                  </failure>
                </testcase>
              </testsuite>
            </testsuites>
            XML;

        $expected = [
            'suites' => [
                [
                    'name' => 'FailedTestSuite',
                    'tests' => 1,
                    'assertions' => 1,
                    'failures' => 1,
                    'errors' => 0,
                    'time' => 0.001,
                    'testcases' => [
                        [
                            'name' => 'testFailure',
                            'class' => 'MyNamespace\FailedTest',
                            'file' => '/path/to/FailedTest.php',
                            'line' => 10,
                            'assertions' => 1,
                            'time' => 0.001,
                            'status' => 'failed',
                            'failure' => [
                                'type' => \PHPUnit\Framework\ExpectationFailedException::class,
                                'message' => 'Failed asserting that false is true.' . "\n" . '/path/to/FailedTest.php:12',
                            ],
                            'error' => null,
                        ],
                    ],
                ],
            ],
            'summary' => [
                'tests' => 1,
                'assertions' => 1,
                'failures' => 1,
                'errors' => 0,
                'time' => 0.001,
            ],
        ];

        $this->assertEquals($expected, $this->jUnitParser->parse($xmlContent));
    }

    public function testParseWithErrorTests(): void
    {
        $xmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="ErrorTestSuite" tests="1" assertions="0" failures="0" errors="1" time="0.001">
                <testcase name="testError" class="MyNamespace\ErrorTest" file="/path/to/ErrorTest.php" line="10" assertions="0" time="0.001">
                  <error type="TypeError" message="Return value must be of type string, null returned">
                    <![CDATA[/path/to/ErrorTest.php:12]]>
                  </error>
                </testcase>
              </testsuite>
            </testsuites>
            XML;

        $expected = [
            'suites' => [
                [
                    'name' => 'ErrorTestSuite',
                    'tests' => 1,
                    'assertions' => 0,
                    'failures' => 0,
                    'errors' => 1,
                    'time' => 0.001,
                    'testcases' => [
                        [
                            'name' => 'testError',
                            'class' => 'MyNamespace\ErrorTest',
                            'file' => '/path/to/ErrorTest.php',
                            'line' => 10,
                            'assertions' => 0,
                            'time' => 0.001,
                            'status' => 'error',
                            'failure' => null,
                            'error' => [
                                'type' => 'TypeError',
                                'message' => 'Return value must be of type string, null returned' . "\n" . '/path/to/ErrorTest.php:12',
                            ],
                        ],
                    ],
                ],
            ],
            'summary' => [
                'tests' => 1,
                'assertions' => 0,
                'failures' => 0,
                'errors' => 1,
                'time' => 0.001,
            ],
        ];

        $this->assertEquals($expected, $this->jUnitParser->parse($xmlContent));
    }

    public function testParseWithMixedTestStatuses(): void
    {
        $xmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="MixedTestSuite" tests="3" assertions="2" failures="1" errors="1" time="0.003">
                <testcase name="testPassed" class="MyNamespace\MixedTest" file="/path/to/MixedTest.php" line="10" assertions="1" time="0.001"/>
                <testcase name="testFailed" class="MyNamespace\MixedTest" file="/path/to/MixedTest.php" line="15" assertions="1" time="0.001">
                  <failure type="PHPUnit\Framework\ExpectationFailedException" message="Failed.">
                    <![CDATA[/path/to/MixedTest.php:17]]>
                  </failure>
                </testcase>
                <testcase name="testError" class="MyNamespace\MixedTest" file="/path/to/MixedTest.php" line="20" assertions="0" time="0.001">
                  <error type="RuntimeException" message="Something went wrong.">
                    <![CDATA[/path/to/MixedTest.php:22]]>
                  </error>
                </testcase>
              </testsuite>
            </testsuites>
            XML;

        $expected = [
            'suites' => [
                [
                    'name' => 'MixedTestSuite',
                    'tests' => 3,
                    'assertions' => 2,
                    'failures' => 1,
                    'errors' => 1,
                    'time' => 0.003,
                    'testcases' => [
                        [
                            'name' => 'testPassed',
                            'class' => 'MyNamespace\MixedTest',
                            'file' => '/path/to/MixedTest.php',
                            'line' => 10,
                            'assertions' => 1,
                            'time' => 0.001,
                            'status' => 'passed',
                            'failure' => null,
                            'error' => null,
                        ],
                        [
                            'name' => 'testFailed',
                            'class' => 'MyNamespace\MixedTest',
                            'file' => '/path/to/MixedTest.php',
                            'line' => 15,
                            'assertions' => 1,
                            'time' => 0.001,
                            'status' => 'failed',
                            'failure' => [
                                'type' => \PHPUnit\Framework\ExpectationFailedException::class,
                                'message' => 'Failed.' . "\n" . '/path/to/MixedTest.php:17',
                            ],
                            'error' => null,
                        ],
                        [
                            'name' => 'testError',
                            'class' => 'MyNamespace\MixedTest',
                            'file' => '/path/to/MixedTest.php',
                            'line' => 20,
                            'assertions' => 0,
                            'time' => 0.001,
                            'status' => 'error',
                            'failure' => null,
                            'error' => [
                                'type' => 'RuntimeException',
                                'message' => 'Something went wrong.' . "\n" . '/path/to/MixedTest.php:22',
                            ],
                        ],
                    ],
                ],
            ],
            'summary' => [
                'tests' => 3,
                'assertions' => 2,
                'failures' => 1,
                'errors' => 1,
                'time' => 0.003,
            ],
        ];

        $this->assertEquals($expected, $this->jUnitParser->parse($xmlContent));
    }

    public function testParseWithEmptyXmlContentThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot parse empty XML content.');
        $this->jUnitParser->parse('');
    }

    public function testParseWithZeroXmlContentThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot parse empty XML content.');
        $this->jUnitParser->parse('0');
    }

    public function testParseWithInvalidXmlContentThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Failed to parse XML: /');
        $this->jUnitParser->parse('this is not xml');
    }

    public function testParseWithXmlContainingNoTestSuites(): void
    {
        $xmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <!-- No testsuite elements here -->
            </testsuites>
            XML;

        $expected = [
            'suites' => [],
            'summary' => [
                'tests' => 0,
                'assertions' => 0,
                'failures' => 0,
                'errors' => 0,
                'time' => 0.0,
            ],
        ];

        $this->assertEquals($expected, $this->jUnitParser->parse($xmlContent));
    }

    public function testParseWithXmlContainingEmptyTestSuites(): void
    {
        $xmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="EmptySuite" tests="0" assertions="0" failures="0" errors="0" time="0.000"/>
            </testsuites>
            XML;

        $expected = [
            'suites' => [], // Should be empty because the testsuite has no testcases
            'summary' => [
                'tests' => 0,
                'assertions' => 0,
                'failures' => 0,
                'errors' => 0,
                'time' => 0.0,
            ],
        ];

        $this->assertEquals($expected, $this->jUnitParser->parse($xmlContent));
    }

    public function testParseWithNestedTestSuites(): void
    {
        $xmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="ParentSuite" tests="2" assertions="2" failures="0" errors="0" time="0.002">
                <testsuite name="ChildSuite" tests="1" assertions="1" failures="0" errors="0" time="0.001">
                  <testcase name="testChild" class="ChildTest" file="/path/to/ChildTest.php" line="5" assertions="1" time="0.001"/>
                </testsuite>
                <testcase name="testParent" class="ParentTest" file="/path/to/ParentTest.php" line="10" assertions="1" time="0.001"/>
              </testsuite>
            </testsuites>
            XML;

        // The XPath `//testsuite[testcase]` should correctly pick up both the child and parent testcases
        // if they are directly under a testsuite.
        // The current implementation will extract the 'ParentSuite' and 'ChildSuite' as separate entries
        // because both contain testcase elements.
        $expected = [
            'suites' => [
                [
                    'name' => 'ParentSuite',
                    'tests' => 2,
                    'assertions' => 2,
                    'failures' => 0,
                    'errors' => 0,
                    'time' => 0.002,
                    'testcases' => [
                        [
                            'name' => 'testParent',
                            'class' => 'ParentTest',
                            'file' => '/path/to/ParentTest.php',
                            'line' => 10,
                            'assertions' => 1,
                            'time' => 0.001,
                            'status' => 'passed',
                            'failure' => null,
                            'error' => null,
                        ],
                    ],
                ],
                [
                    'name' => 'ChildSuite',
                    'tests' => 1,
                    'assertions' => 1,
                    'failures' => 0,
                    'errors' => 0,
                    'time' => 0.001,
                    'testcases' => [
                        [
                            'name' => 'testChild',
                            'class' => 'ChildTest',
                            'file' => '/path/to/ChildTest.php',
                            'line' => 5,
                            'assertions' => 1,
                            'time' => 0.001,
                            'status' => 'passed',
                            'failure' => null,
                            'error' => null,
                        ],
                    ],
                ],
            ],
            'summary' => [
                'tests' => 3,
                'assertions' => 3,
                'failures' => 0,
                'errors' => 0,
                'time' => 0.003,
            ],
        ];

        $result = $this->jUnitParser->parse($xmlContent);

        // Sort suites by name for consistent comparison
        usort($result['suites'], fn ($a, $b) => $a['name'] <=> $b['name']);
        usort($expected['suites'], fn ($a, $b) => $a['name'] <=> $b['name']);

        $this->assertEquals($expected, $result);
    }

    public function testParseWithMissingAttributes(): void
    {
        $xmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="MissingAttrSuite" tests="1" failures="0" errors="0" time="0.001">
                <testcase name="testMissingAssertions" class="MissingAttrTest" file="/path/to/MissingAttrTest.php" line="10" time="0.001"/>
              </testsuite>
            </testsuites>
            XML;

        $expected = [
            'suites' => [
                [
                    'name' => 'MissingAttrSuite',
                    'tests' => 1,
                    'assertions' => 0, // Default to 0
                    'failures' => 0,
                    'errors' => 0,
                    'time' => 0.001,
                    'testcases' => [
                        [
                            'name' => 'testMissingAssertions',
                            'class' => 'MissingAttrTest',
                            'file' => '/path/to/MissingAttrTest.php',
                            'line' => 10,
                            'assertions' => 0, // Default to 0
                            'time' => 0.001,
                            'status' => 'passed',
                            'failure' => null,
                            'error' => null,
                        ],
                    ],
                ],
            ],
            'summary' => [
                'tests' => 1,
                'assertions' => 0,
                'failures' => 0,
                'errors' => 0,
                'time' => 0.001,
            ],
        ];

        $this->assertEquals($expected, $this->jUnitParser->parse($xmlContent));
    }
}
