<?php

namespace PHPUnitGUI\Tests\Unit\Discoverer;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use PHPUnitGUI\Discoverer\TestDiscoverer;

class TestDiscovererTest extends TestCase
{
    private vfsStreamDirectory $vfsStreamDirectory;

    protected function setUp(): void
    {
        $this->vfsStreamDirectory = vfsStream::setup('testRootDir');
    }

    public function testDiscoverFindsTests()
    {
        // 1. Create virtual file system respecting PSR-4
        $testContent = <<<PHP_WRAP
            <?php
            namespace App\\Tests;
            use PHPUnit\\Framework\\TestCase;
            class MyFirstTest extends TestCase 
            {
                public function testSomething() {}
                public function testAnotherThing() {}
            }
            PHP_WRAP;
        vfsStream::newDirectory('Tests')->at($this->vfsStreamDirectory);
        vfsStream::newFile('Tests/MyFirstTest.php')->withContent($testContent)->at($this->vfsStreamDirectory);

        $phpunitConfig = <<<XML
            <phpunit>
                <testsuites>
                    <testsuite name="default">
                        <directory>./Tests</directory>
                    </testsuite>
                </testsuites>
            </phpunit>
            XML;
        vfsStream::newFile('phpunit.xml.dist')->withContent($phpunitConfig)->at($this->vfsStreamDirectory);

        // 2. Run the discoverer
        $testDiscoverer = new TestDiscoverer($this->vfsStreamDirectory->url());
        $results = $testDiscoverer->discover();

        // 3. Assert results
        $this->assertCount(1, $results, 'Should discover exactly one test suite.');
        $suite = $results[0];
        $this->assertEquals('App\Tests\MyFirstTest', $suite['id']);
        $this->assertEquals('MyFirstTest', $suite['name']);
        $this->assertCount(2, $suite['methods'], 'Should discover two test methods.');
        $this->assertEquals('testSomething', $suite['methods'][0]['name']);
        $this->assertEquals('testAnotherThing', $suite['methods'][1]['name']);
    }

    public function testDiscoverHandlesNoConfigFile()
    {
        $testDiscoverer = new TestDiscoverer($this->vfsStreamDirectory->url());
        $results = $testDiscoverer->discover();
        $this->assertEmpty($results);
    }

    public function testDiscoverIgnoresNonTestClasses()
    {
        $nonTestContent = <<<PHP
            <?php
            namespace App;
            class ThisIsATestCase {}
            PHP;
        vfsStream::newDirectory('Tests')->at($this->vfsStreamDirectory);
        vfsStream::newFile('Tests/ThisIsATestCase.php')->withContent($nonTestContent)->at($this->vfsStreamDirectory);

        $phpunitConfig = <<<XML
            <phpunit><testsuites><testsuite name="default"><directory>./Tests</directory></testsuite></testsuites></phpunit>
            XML;
        vfsStream::newFile('phpunit.xml.dist')->withContent($phpunitConfig)->at($this->vfsStreamDirectory);

        $testDiscoverer = new TestDiscoverer($this->vfsStreamDirectory->url());
        $results = $testDiscoverer->discover();

        $this->assertEmpty($results);
    }
}
