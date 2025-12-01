<?php

namespace PhpUnitHub\Util;

class PhpUnitCommandExecutor
{
    public function execute(string $command): ?string
    {
        if ($command === '') {
            return null;
        }

        return shell_exec($command);
    }
}
