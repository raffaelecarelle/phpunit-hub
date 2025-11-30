<?php

namespace PhpUnitHub\Coverage;

use SimpleXMLElement;

class Coverage
{
    public function __construct(private readonly string $coverageXmlPath)
    {
    }

    public function parse(): array
    {
        if (!file_exists($this->coverageXmlPath)) {
            return [];
        }

        // Suppress errors for invalid XML, we'll handle it with the return value
        $xml = @simplexml_load_file($this->coverageXmlPath);
        if ($xml === false || !isset($xml->project)) {
            return [];
        }

        $project = $xml->project;
        $files = [];

        // Discover project root from the <file> paths inside the report
        $projectRoot = $this->discoverProjectRoot($project);

        // Use XPath to find all file nodes within packages.
        $fileNodes = $project->xpath('//package/file');

        if ($fileNodes) {
            foreach ($fileNodes as $file) {
                $filePath = (string)$file['name'];
                if ($projectRoot && str_starts_with($filePath, $projectRoot)) {
                    $filePath = substr($filePath, strlen($projectRoot) + 1); // +1 for the slash
                }

                $metrics = $file->metrics[0] ?? null;
                $coveragePercent = 0.0;
                if ($metrics) {
                    $statements = (int)$metrics['statements'];
                    $coveredStatements = (int)$metrics['coveredstatements'];
                    if ($statements > 0) {
                        $coveragePercent = ($coveredStatements / $statements) * 100;
                    } else {
                        // If a file has no executable statements, consider it 100% covered.
                        $coveragePercent = 100.0;
                    }
                }

                $files[] = [
                    'path' => $filePath,
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

    private function discoverProjectRoot(SimpleXMLElement $project): ?string
    {
        $firstFileNode = $project->xpath('//file[@name]');
        if (!$firstFileNode || !isset($firstFileNode[0]['name'])) {
            return null;
        }

        $fullPath = (string)$firstFileNode[0]['name'];
        // Heuristic to find the project root. Assumes 'src' is a top-level source directory.
        $srcPath = DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
        $srcPos = strpos($fullPath, $srcPath);
        if ($srcPos !== false) {
            // Return the path up to and including the directory before 'src'
            return substr($fullPath, 0, $srcPos);
        }

        return null;
    }
}
