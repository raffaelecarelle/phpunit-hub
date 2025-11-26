<?php

namespace PhpUnitHub\Command;

use Ratchet\Http\HttpServerInterface;

interface RouterInterface extends HttpServerInterface
{
    /**
     * @param string[] $filters
     * @param string[] $suites
     * @param string[] $groups
     * @param array<string, bool> $options
     */
    public function runTests(array $filters, array $suites = [], array $groups = [], array $options = [], bool $isRerun = false): string;

    /**
     * @return string[]
     */
    public function getLastFilters(): array;

    /**
     * @return string[]
     */
    public function getLastSuites(): array;

    /**
     * @return string[]
     */
    public function getLastGroups(): array;

    /**
     * @return array<string, bool>
     */
    public function getLastOptions(): array;
}
