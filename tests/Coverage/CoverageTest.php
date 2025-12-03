<?php

namespace PhpUnitHub\Tests\Coverage;

use Composer\Semver\VersionParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PhpUnitHub\Coverage\Coverage;
use PhpUnitHub\Util\Composer;

use function file_put_contents;
use function mkdir;

#[CoversClass(Coverage::class)]
class CoverageTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/phpunit-gui-coverage-test-' . uniqid('', true);
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
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
                  <file name="$this->tempDir/src/Example.php">
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

    public function testParseFileReturnsCorrectData(): void
    {
        $cloverXmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <coverage generated="1678886400">
              <project timestamp="1678886400">
                <package name="App">
                  <file name="$this->tempDir/src/Example.php">
                    <line num="5" type="stmt" count="1"/>
                    <line num="6" type="stmt" count="0"/>
                  </file>
                </package>
              </project>
            </coverage>
            XML;
        file_put_contents($this->tempDir . '/clover.xml', $cloverXmlContent);

        $sourceContent = <<<PHP
            <?php

            namespace App;

            class Example
            {
                public function method1()
                {
                    return 1;
                }

                public function method2()
                {
                    return 2;
                }
            }
            PHP;
        mkdir($this->tempDir . '/src');
        file_put_contents($this->tempDir . '/src/Example.php', $sourceContent);

        $coverage = new Coverage($this->tempDir, $this->tempDir . '/clover.xml');
        $data = $coverage->parseFile('src/Example.php');

        $this->assertArrayHasKey('lines', $data);
        $this->assertCount(16, $data['lines']);

        // line 5 is covered
        $line5 = $data['lines'][4];
        $this->assertEquals(5, $line5['number']);
        $this->assertEquals('covered', $line5['coverage']);

        // line 6 is uncovered
        $line6 = $data['lines'][5];
        $this->assertEquals(6, $line6['number']);
        $this->assertEquals('uncovered', $line6['coverage']);

        // other lines are neutral
        $line1 = $data['lines'][0];
        $this->assertEquals(1, $line1['number']);
        $this->assertEquals('neutral', $line1['coverage']);
    }

    public function testParseReturnsEmptyArrayForEmptyCoverage(): void
    {
        file_put_contents($this->tempDir . '/clover.xml', '');

        $coverage = new Coverage($this->tempDir, $this->tempDir . '/clover.xml');
        $data = $coverage->parse();

        $this->assertEquals(['total_coverage_percent' => 0.0, 'files' => []], $data);
    }

    public function testParseFileWithNonExistentFile(): void
    {
        $coverage = new Coverage($this->tempDir, $this->tempDir . '/clover.xml');
        $data = $coverage->parseFile('src/NonExistent.php');

        $this->assertEquals(['lines' => []], $data);
    }

    public function testParseWithInvalidXml(): void
    {
        file_put_contents($this->tempDir . '/clover.xml', '<invalid-xml');

        $coverage = new Coverage($this->tempDir, $this->tempDir . '/clover.xml');
        $data = $coverage->parse();

        $this->assertEquals(['total_coverage_percent' => 0.0, 'files' => []], $data);
    }

    public function testParseWithMissingProjectElement(): void
    {
        $cloverXmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <coverage generated="1678886400">
            </coverage>
            XML;
        file_put_contents($this->tempDir . '/clover.xml', $cloverXmlContent);

        $coverage = new Coverage($this->tempDir, $this->tempDir . '/clover.xml');
        $data = $coverage->parse();

        $this->assertEquals(['total_coverage_percent' => 0.0, 'files' => []], $data);
    }

    public function testParseWithFileOutsideSourceDirectories(): void
    {
        $cloverXmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <coverage generated="1678886400">
              <project timestamp="1678886400">
                <metrics files="1" loc="10" ncloc="10" packages="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="2" coveredstatements="2" elements="3" coveredelements="3"/>
                <package name="App">
                  <file name="$this->tempDir/vendor/some/library/src/Example.php">
                    <metrics loc="10" ncloc="10" classes="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="2" coveredstatements="2" elements="3" coveredelements="3"/>
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

        $coverage = new Coverage($this->tempDir, $this->tempDir . '/clover.xml');
        $data = $coverage->parse();

        $this->assertCount(0, $data['files']);
    }

    public function testParseWithNoStatements(): void
    {
        $cloverXmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <coverage generated="1678886400">
              <project timestamp="1678886400">
                <metrics files="1" loc="10" ncloc="10" packages="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="0" coveredstatements="0" elements="1" coveredelements="1"/>
                <package name="App">
                  <file name="$this->tempDir/src/Example.php">
                    <metrics loc="10" ncloc="10" classes="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="0" coveredstatements="0" elements="1" coveredelements="1"/>
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

        $this->assertEquals(100.0, $data['files'][0]['coverage_percent']);
    }

    public function testParseWithNoPhpunit10Config(): void
    {
        $phpunitVersion = Composer::getPackageVersion('phpunit/phpunit', __DIR__ . '/../..');
        $phpunitVersion = (float)(new VersionParser())->normalize($phpunitVersion);

        if ($phpunitVersion < 10) {
            self::markTestSkipped('This test requires PHPUnit 10 or higher.');
        }

        $cloverXmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <coverage generated="1678886400">
              <project timestamp="1678886400">
                <metrics files="1" loc="10" ncloc="10" packages="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="2" coveredstatements="2" elements="3" coveredelements="3"/>
                <package name="App">
                  <file name="$this->tempDir/src/Example.php">
                    <metrics loc="10" ncloc="10" classes="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="2" coveredstatements="2" elements="3" coveredelements="3"/>
                  </file>
                </package>
              </project>
            </coverage>
            XML;
        file_put_contents($this->tempDir . '/clover.xml', $cloverXmlContent);
        mkdir($this->tempDir . '/src');
        file_put_contents($this->tempDir . '/src/Example.php', '<?php namespace App; class Example {}');

        $coverage = new Coverage($this->tempDir, $this->tempDir . '/clover.xml');
        $data = $coverage->parse();

        $this->assertCount(1, $data['files']);
        $this->assertEquals('src/Example.php', $data['files'][0]['path']);
    }

    public function testParseWithNoPhpunit9Config(): void
    {
        $phpunitVersion = Composer::getPackageVersion('phpunit/phpunit', __DIR__ . '/../..');
        $phpunitVersion = (float)(new VersionParser())->normalize($phpunitVersion);

        if ($phpunitVersion >= 10) {
            self::markTestSkipped('This test requires PHPUnit 9 or less.');
        }

        $cloverXmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <coverage generated="1678886400">
              <project timestamp="1678886400">
                <metrics files="1" loc="10" ncloc="10" packages="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="2" coveredstatements="2" elements="3" coveredelements="3"/>
                  <file name="$this->tempDir/src/Example.php">
                    <metrics loc="10" ncloc="10" classes="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="2" coveredstatements="2" elements="3" coveredelements="3"/>
                  </file>
              </project>
            </coverage>
            XML;
        file_put_contents($this->tempDir . '/clover.xml', $cloverXmlContent);
        mkdir($this->tempDir . '/src');
        file_put_contents($this->tempDir . '/src/Example.php', '<?php namespace App; class Example {}');

        $coverage = new Coverage($this->tempDir, $this->tempDir . '/clover.xml');
        $data = $coverage->parse();

        $this->assertCount(1, $data['files']);
        $this->assertEquals('src/Example.php', $data['files'][0]['path']);
    }

    public function testParseFileWithInvalidXml(): void
    {
        file_put_contents($this->tempDir . '/clover.xml', '<invalid-xml');
        mkdir($this->tempDir . '/src');
        file_put_contents($this->tempDir . '/src/Example.php', '<?php');

        $coverage = new Coverage($this->tempDir, $this->tempDir . '/clover.xml');
        $data = $coverage->parseFile('src/Example.php');

        $this->assertEquals(['lines' => []], $data);
    }

    public function testParseFileWithMissingFileNode(): void
    {
        $cloverXmlContent = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <coverage generated="1678886400">
              <project timestamp="1678886400">
                <package name="App">
                </package>
              </project>
            </coverage>
            XML;
        file_put_contents($this->tempDir . '/clover.xml', $cloverXmlContent);
        mkdir($this->tempDir . '/src');
        file_put_contents($this->tempDir . '/src/Example.php', '<?php');

        $coverage = new Coverage($this->tempDir, $this->tempDir . '/clover.xml');
        $data = $coverage->parseFile('src/Example.php');

        $this->assertNotEmpty($data['lines']);
        foreach ($data['lines'] as $line) {
            $this->assertEquals('neutral', $line['coverage']);
        }
    }
}
