<?php

namespace PhpUnitHub\Coverage;

use SimpleXMLElement;

use function property_exists;

class Coverage
{
    private readonly ?SimpleXMLElement $config;

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $coverageXmlPath
    ) {
        $this->config = $this->loadConfiguration();
    }

    public function hasDriver(): bool
    {
        return extension_loaded('xdebug') || extension_loaded('pcov');
    }

    /**
     * @return array{total_coverage_percent: float, files: array<array{path: string, coverage_percent: float}>}
     */
    public function parse(): array
    {
        if (!file_exists($this->coverageXmlPath)) {
            return ['total_coverage_percent' => 0.0, 'files' => []];
        }

        $xml = @simplexml_load_string(file_get_contents($this->coverageXmlPath));
        if ($xml === false || (!property_exists($xml, 'project') || $xml->project === null)) {
            return ['total_coverage_percent' => 0.0, 'files' => []];
        }

        $project = $xml->project;
        $files = [];
        $sourceDirectories = $this->getSourceDirectories();

        $fileNodes = $project->xpath('//package/file') !== [] ? $project->xpath('//package/file') : $project->xpath('//file');

        if ($fileNodes !== []) {
            foreach ($fileNodes as $fileNode) {
                $filePath = (string)$fileNode['name'];
                $relativePath = $this->getRelativePath($filePath, $sourceDirectories);

                if ($relativePath === null) {
                    continue;
                }

                $metrics = $fileNode->metrics[0] ?? null;
                $coveragePercent = 0.0;
                if ($metrics) {
                    $statements = (int)$metrics['statements'];
                    $coveredStatements = (int)$metrics['coveredstatements'];
                    $coveragePercent = $statements > 0 ? ($coveredStatements / $statements) * 100 : 100.0;
                }

                $files[] = [
                    'path' => $relativePath,
                    'coverage_percent' => $coveragePercent,
                ];
            }
        }

        $totalMetrics = $project->metrics[0] ?? null;
        $totalCoverage = 0.0;
        if ($totalMetrics) {
            $statements = (int)$totalMetrics['statements'];
            $coveredStatements = (int)$totalMetrics['coveredstatements'];
            if ($statements > 0) {
                $totalCoverage = ($coveredStatements / $statements) * 100;
            }
        }

        return [
            'total_coverage_percent' => $totalCoverage,
            'files' => $files,
        ];
    }

    /**
     * @return array{
     *     lines: array<array{
     *         number: int,
     *         tokens: array<array{type: string, value: string}>,
     *         coverage: string
     *     }>
     * }
     */
    public function parseFile(string $filePath): array
    {
        $fullPath = $this->projectRoot . '/' . $filePath;

        if (!file_exists($fullPath)) {
            return ['lines' => []];
        }

        $xml = @simplexml_load_string(file_get_contents($this->coverageXmlPath));
        if ($xml === false) {
            return ['lines' => []];
        }

        $fileNode = $xml->xpath(sprintf("//file[@name='%s']", $fullPath))[0] ?? null;
        $coverageData = [];
        if ($fileNode) {
            foreach ($fileNode->line as $line) {
                $attrs = $line->attributes();
                if ($attrs) {
                    $coverageData[(int)$attrs['num']] = (int)$attrs['count'] > 0 ? 'covered' : 'uncovered';
                }
            }
        }

        $sourceCode = file_get_contents($fullPath);
        $tokens = token_get_all($sourceCode);

        $linesByNumber = [];
        $currentLineNumber = 1;
        $currentLineTokens = [];

        foreach ($tokens as $token) {
            $tokenValue = is_array($token) ? $token[1] : $token;
            $tokenType = is_array($token) ? token_name($token[0]) : 'T_STRING';

            $newlineCount = substr_count($tokenValue, "\n");

            if ($newlineCount === 0) {
                $currentLineTokens[] = ['type' => $tokenType, 'value' => $tokenValue];
            } else {
                $parts = explode("\n", $tokenValue);

                if ($parts[0] !== '') {
                    $currentLineTokens[] = ['type' => $tokenType, 'value' => $parts[0]];
                }

                $counter = count($parts);

                for ($i = 1; $i < $counter; $i++) {
                    $linesByNumber[$currentLineNumber] = $currentLineTokens;
                    $currentLineNumber++;
                    $currentLineTokens = [];

                    if ($parts[$i] !== '') {
                        $currentLineTokens[] = ['type' => $tokenType, 'value' => $parts[$i]];
                    }
                }
            }
        }

        if ($currentLineTokens !== [] || $currentLineNumber === 1) {
            $linesByNumber[$currentLineNumber] = $currentLineTokens;
        }

        $lines = [];
        foreach ($linesByNumber as $num => $tokens) {
            $lines[] = [
                'number' => $num,
                'tokens' => $tokens,
                'coverage' => $coverageData[$num] ?? 'neutral',
            ];
        }

        return ['lines' => $lines];
    }

    private function loadConfiguration(): ?SimpleXMLElement
    {
        $configPath = $this->projectRoot . '/phpunit.xml';
        if (!file_exists($configPath)) {
            $configPath = $this->projectRoot . '/phpunit.xml.dist';
            if (!file_exists($configPath)) {
                return null;
            }
        }

        return @simplexml_load_string(file_get_contents($configPath));
    }

    /**
     * @return string[]
     */
    private function getSourceDirectories(): array
    {
        if (!$this->config instanceof SimpleXMLElement) {
            return [];
        }

        // PHPUnit >= 10
        if (property_exists($this->config, 'source') && $this->config->source !== null) {
            if (!property_exists($this->config->source->include, 'directory')) {
                return [];
            }

            if ($this->config->source->include->directory === null) {
                return [];
            }

            $directories = [];
            foreach ($this->config->source->include->directory as $dir) {
                $directories[] = (string)$dir;
            }

            return $directories;
        }

        // PHPUnit < 10
        if (property_exists($this->config, 'coverage') && $this->config->coverage !== null) {
            if (!property_exists($this->config->coverage->include, 'directory')) {
                return [];
            }

            if ($this->config->coverage->include->directory === null) {
                return [];
            }

            $directories = [];
            foreach ($this->config->coverage->include->directory as $dir) {
                $directories[] = (string)$dir;
            }

            return $directories;
        }

        return [];
    }

    /**
     * @param array<string> $sourceDirectories
     */
    private function getRelativePath(string $fullPath, array $sourceDirectories): ?string
    {
        foreach ($sourceDirectories as $sourceDirectory) {
            $sourcePath = $this->projectRoot . DIRECTORY_SEPARATOR . $sourceDirectory;
            if (str_starts_with($fullPath, $sourcePath)) {
                return substr($fullPath, strlen($this->projectRoot) + 1);
            }
        }

        return null;
    }
}
