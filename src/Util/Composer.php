<?php

namespace PhpUnitHub\Util;

use function file_get_contents;
use function is_array;
use function is_readable;
use function is_string;
use function json_decode;
use function preg_match;
use function rtrim;

class Composer
{
    public static function getComposerBinDir(string $projectDir): string
    {
        $composerFile = rtrim($projectDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'composer.json';

        if (!is_readable($composerFile)) {
            return $projectDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        }

        $data = json_decode(file_get_contents($composerFile), true);
        if (!is_array($data)) {
            return $projectDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        }

        $binDir = $data['config']['bin-dir'] ?? null;
        if (!$binDir || !is_string($binDir)) {
            return $projectDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        }

        // Resolve relative paths to absolute
        if (!preg_match('#^(?:/|[A-Za-z]:\\\\|\\\\)#', $binDir)) {
            $binDir = $projectDir . DIRECTORY_SEPARATOR . $binDir;
        }

        return rtrim($binDir, DIRECTORY_SEPARATOR);
    }
}
