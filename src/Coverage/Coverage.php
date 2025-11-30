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

        $xml = simplexml_load_file($this->coverageXmlPath);
        if ($xml === false || !isset($xml->project)) {
            return [];
        }

        $project = $xml->project;
        $files = [];

        // Determine project root from the <file> paths inside the report
        $projectRoot = $this->discoverProjectRoot($project);

        $fileNodes = $project->xpath('//file[@name]');

        if ($fileNodes) {
            foreach ($fileNodes as $file) {
                $filePath = (string)$file['name'];
                if ($projectRoot && str_starts_with($filePath, $projectRoot)) {
                    $filePath = substr($filePath, strlen($projectRoot));
                }

                $lines = $file->totals->lines ?? null;
                $files[] = [
                    'path' => $filePath,
                    'coverage_percent' => $lines ? (float)$lines['percent'] : 0.0,
                ];
            }
        }

        $totalLines = $project->totals->lines ?? null;
        $totalCoverage = $totalLines ? (float)$totalLines['percent'] : 0.0;

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
        $srcPath = 'src' . DIRECTORY_SEPARATOR;

        $srcPos = strpos($fullPath, $srcPath);
        if ($srcPos !== false) {
            return substr($fullPath, 0, $srcPos);
        }

        return null;
    }
}
