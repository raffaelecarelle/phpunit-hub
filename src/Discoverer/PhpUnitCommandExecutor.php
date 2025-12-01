<?php

namespace PhpUnitHub\Discoverer;

class PhpUnitCommandExecutor
{
    public function execute(string $command): ?string
    {
        return shell_exec($command);
    }
}
