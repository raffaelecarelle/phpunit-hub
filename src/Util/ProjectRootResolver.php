<?php

namespace PhpUnitHub\Util;

use function getcwd;

class ProjectRootResolver
{
    public function resolve(string $startPath = __DIR__): string
    {
        $currentPath = $startPath;

        while ($currentPath !== dirname($currentPath)) {
            if (file_exists($currentPath . '/vendor/autoload.php')) {
                return $currentPath;
            }

            $currentPath = dirname($currentPath);
        }

        return getcwd();
    }
}
