<?php

namespace LaravelExporter;

use Closure;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use LaravelExporter\Formats\CsvExporter;
use LaravelExporter\Formats\ExcelExporter;
use LaravelExporter\Formats\JsonExporter;
use LaravelExporter\Formats\PhpSpreadsheetExporter;
use LaravelExporter\Contracts\FormatExporterInterface;

class DataExporter
{
    protected object|array $source;
    protected Exporter $config;
    protected array $extractedHeaders = [];

    public function __construct(object|array $source, Exporter $config)
    {
        $this->source = $source;
        $this->config = $config;
    }

    /**
     * Export to a file
     */
    public function toFile(string $path): bool
    {
        $exporter = $this->getFormatExporter();
        return $exporter->export($this->getDataGenerator(), $this->resolveHeaders(), $path);
    }

    /**
     * Export and download (for HTTP responses)
     */
    public function download(?string $filename = null): mixed
    {
        $filename = $filename ?? $this->config->getFilename();
        $exporter = $this->getFormatExporter();

        return $exporter->download($this->getDataGenerator(), $this->resolveHeaders(), $filename);
    }

    /**
     * Export to string
     */
    public function toString(): string
    {
        $exporter = $this->getFormatExporter();
        return $exporter->toString($this->getDataGenerator(), $this->resolveHeaders());
    }

    /**
     * Stream the export (memory efficient for large datasets)
     */
    public function stream(?string $filename = null): mixed
    {
        $filename = $filename ?? $this->config->getFilename();
        $exporter = $this->getFormatExporter();

        return $exporter->stream($this->getDataGenerator(), $this->resolveHeaders(), $filename);
    }

    /**
     * Get data as a generator for memory efficiency
     */
    protected function getDataGenerator(): Generator
    {
        $columns = $this->config->getColumns();
        $transformer = $this->config->getRowTransformer();
        $chunkSize = $this->config->getChunkSize();
        $firstRow = true;

        // Handle Eloquent Builder with chunking for large datasets
        if ($this->source instanceof Builder) {
            foreach ($this->source->lazy($chunkSize) as $item) {
                $row = $this->processRow($item, $columns, $transformer);
                if ($firstRow) {
                    $this->extractedHeaders = array_keys($row);
                    $firstRow = false;
                }
                yield $row;
            }
            return;
        }

        // Handle LazyCollection (already memory efficient)
        if ($this->source instanceof LazyCollection) {
            foreach ($this->source as $item) {
                $row = $this->processRow($item, $columns, $transformer);
                if ($firstRow) {
                    $this->extractedHeaders = array_keys($row);
                    $firstRow = false;
                }
                yield $row;
            }
            return;
        }

        // Handle Collection
        if ($this->source instanceof Collection) {
            foreach ($this->source->lazy() as $item) {
                $row = $this->processRow($item, $columns, $transformer);
                if ($firstRow) {
                    $this->extractedHeaders = array_keys($row);
                    $firstRow = false;
                }
                yield $row;
            }
            return;
        }

        // Handle arrays and objects
        $items = is_object($this->source) && !is_iterable($this->source)
            ? [$this->source]
            : $this->source;

        foreach ($items as $item) {
            $row = $this->processRow($item, $columns, $transformer);
            if ($firstRow) {
                $this->extractedHeaders = array_keys($row);
                $firstRow = false;
            }
            yield $row;
        }
    }

    /**
     * Process a single row
     */
    protected function processRow(mixed $item, array $columns, ?Closure $transformer): array
    {
        // Convert to array if needed
        $data = $this->toArray($item);

        // Apply column filtering
        if (!empty($columns)) {
            $filtered = [];
            foreach ($columns as $key => $column) {
                $alias = is_string($key) ? $key : $column;
                $filtered[$alias] = $this->getNestedValue($data, $column);
            }
            $data = $filtered;
        }

        // Apply custom transformer
        if ($transformer) {
            $data = $transformer($data, $item);
        }

        return $data;
    }

    /**
     * Convert item to array
     */
    protected function toArray(mixed $item): array
    {
        if (is_array($item)) {
            return $item;
        }

        if ($item instanceof Model) {
            return $item->toArray();
        }

        if ($item instanceof \stdClass) {
            return (array) $item;
        }

        if (is_object($item)) {
            if (method_exists($item, 'toArray')) {
                return $item->toArray();
            }
            return get_object_vars($item);
        }

        return [$item];
    }

    /**
     * Get nested value using dot notation
     */
    protected function getNestedValue(array $data, string $key): mixed
    {
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            $value = $data;
            foreach ($keys as $k) {
                $value = $value[$k] ?? null;
                if ($value === null) break;
            }
            return $value;
        }

        return $data[$key] ?? null;
    }

    /**
     * Resolve headers (custom or extracted from data)
     */
    protected function resolveHeaders(): array
    {
        $customHeaders = $this->config->getHeaders();

        if (!empty($customHeaders)) {
            return $customHeaders;
        }

        // If columns are defined with aliases, use them as headers
        $columns = $this->config->getColumns();
        if (!empty($columns)) {
            $headers = [];
            foreach ($columns as $key => $column) {
                $headers[] = is_string($key) ? $key : $this->formatHeader($column);
            }
            return $headers;
        }

        // Extract headers from first row (need to peek at data)
        if (empty($this->extractedHeaders)) {
            foreach ($this->getDataGenerator() as $row) {
                $this->extractedHeaders = array_keys($row);
                break;
            }
        }

        return array_map([$this, 'formatHeader'], $this->extractedHeaders);
    }

    /**
     * Format a column name into a readable header
     */
    protected function formatHeader(string $column): string
    {
        // Remove dots for nested columns, take last part
        if (str_contains($column, '.')) {
            $parts = explode('.', $column);
            $column = end($parts);
        }

        return ucwords(str_replace(['_', '-'], ' ', $column));
    }

    /**
     * Get the appropriate format exporter
     */
    protected function getFormatExporter(): FormatExporterInterface
    {
        $format = $this->config->getFormat();
        $options = $this->config->getFormatOptions();

        // Use PhpSpreadsheet for advanced Excel features
        if (in_array($format, ['xlsx', 'excel']) && $this->config->hasAdvancedExcel()) {
            if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                throw new \RuntimeException(
                    'PhpSpreadsheet is required for advanced Excel features. ' .
                    'Install it with: composer require phpoffice/phpspreadsheet'
                );
            }
            return new PhpSpreadsheetExporter($options);
        }

        return match ($format) {
            'csv' => new CsvExporter($options),
            'xlsx', 'excel' => new ExcelExporter($options),
            'json' => new JsonExporter($options),
            default => throw new \InvalidArgumentException("Unsupported export format: {$format}"),
        };
    }
}
