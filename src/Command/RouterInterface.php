<?php

namespace PHPUnitGUI\Command;

use Ratchet\Http\HttpServerInterface;

interface RouterInterface extends HttpServerInterface
{
    public function runTests(array $filters, bool $isRerun = false): void;

    public function getLastFilters(): array;
}
