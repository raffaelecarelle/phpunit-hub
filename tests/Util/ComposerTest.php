<?php

namespace PhpUnitHub\Tests\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PhpUnitHub\TestRunner\TestRunner;
use PhpUnitHub\Util\Composer;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(Composer::class)]
class ComposerTest extends TestCase
{
    private string $tempDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/phpunit-gui-composer-test-' . uniqid('', true);
        $this->filesystem->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
        parent::tearDown();
    }

    public function testGetComposerBinDirWithNoComposerJson(): void
    {
        $binDir = Composer::getComposerBinDir($this->tempDir);
        $this->assertEquals($this->tempDir . '/vendor/bin', $binDir);
    }

    public function testGetComposerBinDirWithInvalidComposerJson(): void
    {
        $this->filesystem->dumpFile($this->tempDir . '/composer.json', 'invalid json');
        $binDir = Composer::getComposerBinDir($this->tempDir);
        $this->assertEquals($this->tempDir . '/vendor/bin', $binDir);
    }

    public function testGetComposerBinDirWithNoBinDirInComposerJson(): void
    {
        $this->filesystem->dumpFile($this->tempDir . '/composer.json', '{}');
        $binDir = Composer::getComposerBinDir($this->tempDir);
        $this->assertEquals($this->tempDir . '/vendor/bin', $binDir);
    }

    public function testGetComposerBinDirWithBinDirInComposerJson(): void
    {
        $this->filesystem->dumpFile($this->tempDir . '/composer.json', '{"config": {"bin-dir": "my-bin"}}');
        $binDir = Composer::getComposerBinDir($this->tempDir);
        $this->assertEquals($this->tempDir . '/my-bin', $binDir);
    }
}
