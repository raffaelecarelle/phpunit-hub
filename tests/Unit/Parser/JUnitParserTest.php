<?php

namespace PHPUnitGUI\Tests\Unit\Parser;

use PHPUnit\Framework\TestCase;
use PHPUnitGUI\Parser\JUnitParser;

class JUnitParserTest extends TestCase
{
    private JUnitParser $jUnitParser;

    protected function setUp(): void
    {
        $this->jUnitParser = new JUnitParser();
    }

    public function testParseValidXml(): void
    {
        $xmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
                <testsuite name="My\App\Tests\ExampleTest" tests="3" assertions="3" failures="1" errors="1" time="0.1">
                    <testcase name="testSuccess" class="My\App\Tests\ExampleTest" assertions="1" time="0.05" />
                    <testcase name="testFailure" class="My\App\Tests\ExampleTest" assertions="1" time="0.03">
                        <failure type="PHPUnit\Framework\ExpectationFailedException">
                            Failed asserting that false is true.
                        </failure>
                    </testcase>
                    <testcase name="testError" class="My\App\Tests\ExampleTest" assertions="1" time="0.02">
                        <error type="TypeError">
                            Something went wrong.
                        </error>
                    </testcase>
                </testsuite>
            </testsuites>
            XML;

        $results = $this->jUnitParser->parse($xmlContent);

        $this->assertCount(1, $results['suites']);
        $this->assertEquals([
            'tests' => 3,
            'assertions' => 3,
            'failures' => 1,
            'errors' => 1,
            'time' => 0.1,
        ], $results['summary']);

        $suite = $results['suites'][0];
        $this->assertCount(3, $suite['testcases']);

        // Test case 1: Success
        $this->assertEquals('passed', $suite['testcases'][0]['status']);

        // Test case 2: Failure
        $this->assertEquals(\PHPUnit\Framework\ExpectationFailedException::class, $suite['testcases'][1]['failure']['type']);
        $this->assertStringContainsString('Failed asserting that false is true', $suite['testcases'][1]['failure']['message']);

        // Test case 3: Error
        $this->assertEquals('error', $suite['testcases'][2]['status']);
        $this->assertEquals('TypeError', $suite['testcases'][2]['error']['type']);
        $this->assertStringContainsString('Something went wrong', $suite['testcases'][2]['error']['message']);
    }

    public function testParseEmptyXml(): void
    {
        $this->expectException(\Exception::class);
        $this->jUnitParser->parse('');
    }

    public function testParseMalformedXml(): void
    {
        $this->expectException(\Exception::class);
        $this->jUnitParser->parse('<testsuite><testcase...></testsuite>');
    }
}
