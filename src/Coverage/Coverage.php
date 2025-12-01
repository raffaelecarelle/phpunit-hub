<?php

namespace PhpUnitHub\Coverage;

use SimpleXMLElement;

class Coverage
{
    private readonly ?SimpleXMLElement $config;

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $coverageXmlPath
    ) {
        $this->config = $this->loadConfiguration();
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

        $fileNodes = $project->xpath('//package/file');

        if ($fileNodes) {
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
        if (!$this->config instanceof SimpleXMLElement || (!property_exists($this->config->source->include, 'directory') || $this->config->source->include->directory === null)) {
            return ['src']; // Default
        }

        $directories = [];
        foreach ($this->config->source->include->directory as $dir) {
            $directories[] = (string)$dir;
        }

        return $directories;
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
