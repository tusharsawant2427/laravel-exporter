<?php

namespace LaravelExporter\Readers;

use Generator;
use LaravelExporter\Contracts\FormatReaderInterface;

/**
 * JSON File Reader
 *
 * Supports both array of objects and nested structures
 */
class JsonReader implements FormatReaderInterface
{
    /**
     * Read the JSON file and yield rows
     */
    public function read(string $filePath, array $options = []): Generator
    {
        $startRow = $options['start_row'] ?? 1;
        $limit = $options['limit'] ?? 0;
        $dataPath = $options['data_path'] ?? null; // e.g., 'data.items'

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Cannot read file: {$filePath}");
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON: " . json_last_error_msg());
        }

        // Navigate to data path if specified
        if ($dataPath !== null) {
            $data = $this->getNestedValue($data, $dataPath);
            if ($data === null) {
                throw new \RuntimeException("Data path '{$dataPath}' not found in JSON");
            }
        }

        // Ensure we have an array
        if (!is_array($data)) {
            throw new \RuntimeException("JSON data must be an array");
        }

        // Check if it's an associative array (single record) vs indexed array (multiple records)
        if ($this->isAssociative($data)) {
            $data = [$data];
        }

        $rowNumber = 0;
        $importedCount = 0;

        foreach ($data as $row) {
            $rowNumber++;

            // Skip rows before start row
            if ($rowNumber < $startRow) {
                continue;
            }

            // Flatten nested arrays to single level
            $flatRow = $this->flattenRow($row);

            yield $rowNumber => $flatRow;

            $importedCount++;

            // Check limit
            if ($limit > 0 && $importedCount >= $limit) {
                break;
            }
        }
    }

    /**
     * Get row count from JSON
     */
    public function getRowCount(string $filePath): ?int
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        if ($this->isAssociative($data)) {
            return 1;
        }

        return count($data);
    }

    /**
     * JSON files only have one "sheet"
     */
    public function getSheets(string $filePath): array
    {
        return [0 => 'Sheet1'];
    }

    /**
     * Check supported extensions
     */
    public function supports(string $extension): bool
    {
        return strtolower($extension) === 'json';
    }

    /**
     * Get nested value using dot notation
     */
    protected function getNestedValue(array $data, string $path): mixed
    {
        $keys = explode('.', $path);

        foreach ($keys as $key) {
            if (!is_array($data) || !isset($data[$key])) {
                return null;
            }
            $data = $data[$key];
        }

        return $data;
    }

    /**
     * Check if array is associative
     */
    protected function isAssociative(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Flatten nested arrays
     */
    protected function flattenRow(array $row, string $prefix = ''): array
    {
        $result = [];

        foreach ($row as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value) && $this->isAssociative($value)) {
                $result = array_merge($result, $this->flattenRow($value, $newKey));
            } else {
                $result[$newKey] = is_array($value) ? json_encode($value) : $value;
            }
        }

        return $result;
    }
}
