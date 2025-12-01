<?php

namespace PhpUnitHub\Tests\Coverage;

use PHPUnit\Framework\TestCase;
use PhpUnitHub\Coverage\Coverage;

class CoverageTest extends TestCase
{
    private string $tempDir;

    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/phpunit-gui-coverage-test-' . uniqid();
        mkdir($this->tempDir, 0o777, true);

        $this->originalCwd = getcwd();
        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }

            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    public function testParseReturnsCorrectData(): void
    {
        $cloverXmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <coverage generated="1678886400">
              <project timestamp="1678886400">
                <metrics files="1" loc="10" ncloc="10" packages="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="2" coveredstatements="2" elements="3" coveredelements="3"/>
                <package name="App">
                  <file name="{$this->tempDir}/src/Example.php">
                    <metrics loc="10" ncloc="10" classes="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="2" coveredstatements="2" elements="3" coveredelements="3"/>
                    <class name="App\Example" namespace="App">
                      <metrics complexity="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="2" coveredstatements="2" elements="3" coveredelements="3"/>
                    </class>
                  </file>
                </package>
              </project>
            </coverage>
            XML;
        file_put_contents($this->tempDir . '/clover.xml', $cloverXmlContent);

        $phpunitXmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <phpunit>
                <source>
                    <include>
                        <directory>src</directory>
                    </include>
                </source>
            </phpunit>
            XML;
        file_put_contents($this->tempDir . '/phpunit.xml', $phpunitXmlContent);
        mkdir($this->tempDir . '/src');
        file_put_contents($this->tempDir . '/src/Example.php', '<?php namespace App; class Example {}');

        $coverage = new Coverage($this->tempDir, $this->tempDir . '/clover.xml');
        $data = $coverage->parse();

        $this->assertArrayHasKey('total_coverage_percent', $data);
        $this->assertEquals(100.0, $data['total_coverage_percent']);
        $this->assertArrayHasKey('files', $data);
        $this->assertCount(1, $data['files']);
        $this->assertEquals('src/Example.php', $data['files'][0]['path']);
        $this->assertEquals(100.0, $data['files'][0]['coverage_percent']);
    }
}
