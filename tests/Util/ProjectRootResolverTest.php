<?php

namespace PhpUnitHub\Tests\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PhpUnitHub\Util\ProjectRootResolver;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(ProjectRootResolver::class)]
class ProjectRootResolverTest extends TestCase
{
    private string $tempDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/phpunit-gui-project-root-resolver-test-' . uniqid('', true);
        $this->filesystem->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
        parent::tearDown();
    }

    public function testResolveWithVendorDirInCurrentDir(): void
    {
        $this->filesystem->mkdir($this->tempDir . '/vendor');
        $this->filesystem->dumpFile($this->tempDir . '/vendor/autoload.php', '<?php');

        $projectRootResolver = new ProjectRootResolver();
        $this->assertEquals($this->tempDir, $projectRootResolver->resolve($this->tempDir));
    }

    public function testResolveWithVendorDirInParentDir(): void
    {
        $this->filesystem->mkdir($this->tempDir . '/vendor');
        $this->filesystem->dumpFile($this->tempDir . '/vendor/autoload.php', '<?php');
        $this->filesystem->mkdir($this->tempDir . '/subdir');

        $projectRootResolver = new ProjectRootResolver();
        $this->assertEquals($this->tempDir, $projectRootResolver->resolve($this->tempDir . '/subdir'));
    }

    public function testResolveWithNoVendorDir(): void
    {
        $projectRootResolver = new ProjectRootResolver();
        $this->assertEquals(getcwd(), $projectRootResolver->resolve($this->tempDir));
    }
}
