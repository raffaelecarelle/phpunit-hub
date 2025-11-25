<?php

namespace PhpUnitHub\Command;

use Ratchet\Http\HttpServerInterface;

interface RouterInterface extends HttpServerInterface
{
    /**
     * @param string[] $filters
     */
    public function runTests(array $filters, bool $isRerun = false): void;

    /**
     * @return string[]
     */
    public function getLastFilters(): array;
}
