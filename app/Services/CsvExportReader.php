<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class CsvExportReader
{
    public function resolveDirectory(?string $path = null): string
    {
        $path = $path ?: config('resolutions.csv_export_path');

        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    public function findNewest(string $directory, string $prefix): ?string
    {
        if (! File::isDirectory($directory)) {
            return null;
        }

        $matches = glob($directory.DIRECTORY_SEPARATOR.$prefix.'*.csv') ?: [];

        if ($matches === []) {
            return null;
        }

        usort($matches, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $matches[0];
    }

    /**
     * @return \Generator<int, array<string, string|null>>
     */
    public function rows(string $filePath): \Generator
    {
        foreach ($this->indexedRows($filePath) as $row) {
            yield $row['assoc'];
        }
    }

    /**
     * @return \Generator<int, array{assoc: array<string, string|null>, columns: list<string|null>}>
     */
    public function indexedRows(string $filePath): \Generator
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return;
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);

            return;
        }

        $headers = array_map(fn ($h) => trim((string) $h, '"'), $headers);

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }

            $columns = [];
            $assoc = [];

            foreach ($headers as $i => $header) {
                $value = isset($row[$i]) ? $this->cleanValue($row[$i]) : null;
                $columns[$i] = $value;
                $assoc[$header] = $value;
            }

            yield [
                'assoc' => $assoc,
                'columns' => $columns,
            ];
        }

        fclose($handle);
    }

    protected function cleanValue(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
