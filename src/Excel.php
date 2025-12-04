<?php

namespace LaravelExporter;

use Generator;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use LaravelExporter\Concerns\FromCollection;
use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\FromArray;
use LaravelExporter\Concerns\FromGenerator;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\WithMapping;
use LaravelExporter\Concerns\WithColumnFormatting;
use LaravelExporter\Concerns\WithColumnWidths;
use LaravelExporter\Concerns\WithStyles;
use LaravelExporter\Concerns\WithTitle;
use LaravelExporter\Concerns\WithMultipleSheets;
use LaravelExporter\Concerns\WithTotals;
use LaravelExporter\Concerns\WithReportHeader;
use LaravelExporter\Concerns\WithColumnDefinitions;
use LaravelExporter\Concerns\WithConditionalColoring;
use LaravelExporter\Concerns\WithEvents;
use LaravelExporter\Concerns\WithFreezeRow;
use LaravelExporter\Concerns\WithAutoFilter;
use LaravelExporter\Concerns\WithChunkReading;
use LaravelExporter\Concerns\UseChunkedWriter;
use LaravelExporter\Concerns\UseStyledOpenSpout;
use LaravelExporter\Concerns\UseHybridExporter;
use LaravelExporter\Concerns\WithConditionalFormatting;
use LaravelExporter\Concerns\ShouldAutoSize;
use LaravelExporter\Imports\ImportResult;
use LaravelExporter\Formats\CsvExporter;
use LaravelExporter\Formats\ExcelExporter;
use LaravelExporter\Formats\JsonExporter;
use LaravelExporter\Formats\PhpSpreadsheetExporter;
use LaravelExporter\Formats\ChunkedPhpSpreadsheetExporter;
use LaravelExporter\Formats\StyledOpenSpoutExporter;
use LaravelExporter\Formats\HybridExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel Manager - Maatwebsite-style class-based exports
 *
 * Usage:
 *   Excel::download(new UsersExport, 'users.xlsx');
 *   Excel::store(new UsersExport, 'exports/users.xlsx', 'local');
 */
class Excel
{
    /**
     * Download the export as a file
     */
    public function download(object $export, string $filename, ?string $writerType = null): BinaryFileResponse|StreamedResponse
    {
        $writerType = $writerType ?? $this->detectWriterType($filename);
        $exporter = $this->createExporter($export, $writerType);

        return $exporter->download(
            $this->getData($export),
            $this->getHeadings($export),
            $filename
        );
    }

    /**
     * Store the export to disk
     */
    public function store(object $export, string $path, ?string $disk = null, ?string $writerType = null): bool
    {
        $writerType = $writerType ?? $this->detectWriterType($path);
        $exporter = $this->createExporter($export, $writerType);

        // Create temp file
        $tempPath = sys_get_temp_dir() . '/' . uniqid('export_') . '.' . $writerType;

        $result = $exporter->export(
            $this->getData($export),
            $this->getHeadings($export),
            $tempPath
        );

        if ($result && $disk) {
            Storage::disk($disk)->put($path, file_get_contents($tempPath));
            @unlink($tempPath);
            return true;
        }

        // Move to final location (storage/app by default if no disk specified)
        if ($result) {
            $finalPath = $this->isAbsolutePath($path) ? $path : storage_path('app/' . $path);

            // Ensure directory exists
            $dir = dirname($finalPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Use copy + delete for cross-device compatibility
            if (copy($tempPath, $finalPath)) {
                @unlink($tempPath);
                return true;
            }

            @unlink($tempPath);
            return false;
        }

        return $result;
    }

    /**
     * Check if path is absolute
     */
    protected function isAbsolutePath(string $path): bool
    {
        // Windows or Unix absolute path
        return preg_match('/^([a-zA-Z]:)?[\\/]/', $path) === 1;
    }

    /**
     * Queue the export (placeholder for future implementation)
     */
    public function queue(object $export, string $path, ?string $disk = null, ?string $writerType = null)
    {
        // For now, just store synchronously
        // Future: dispatch to queue
        return $this->store($export, $path, $disk, $writerType);
    }

    /**
     * Get raw export content
     */
    public function raw(object $export, ?string $writerType = null): string
    {
        $writerType = $writerType ?? 'xlsx';
        $exporter = $this->createExporter($export, $writerType);

        return $exporter->toString(
            $this->getData($export),
            $this->getHeadings($export)
        );
    }

    /**
     * Get data from export class
     */
    protected function getData(object $export): Generator
    {
        $data = $this->getSource($export);
        $hasMapping = $export instanceof WithMapping;

        // Convert to generator
        foreach ($data as $row) {
            if ($hasMapping) {
                yield $export->map($row);
            } elseif (is_object($row) && method_exists($row, 'toArray')) {
                yield $row->toArray();
            } elseif (is_array($row)) {
                yield $row;
            } else {
                yield (array) $row;
            }
        }
    }

    /**
     * Get source data from export class
     */
    protected function getSource(object $export): iterable
    {
        if ($export instanceof FromCollection) {
            return $export->collection();
        }

        if ($export instanceof FromQuery) {
            $query = $export->query();

            // Use chunking if WithChunkReading is implemented
            if ($export instanceof WithChunkReading) {
                return $this->getChunkedData($query, $export->chunkSize());
            }

            // Default to cursor for memory efficiency
            return $query->cursor();
        }

        if ($export instanceof FromArray) {
            return $export->array();
        }

        if ($export instanceof FromGenerator) {
            return $export->generator();
        }

        throw new \InvalidArgumentException(
            'Export class must implement FromCollection, FromQuery, FromArray, or FromGenerator'
        );
    }

    /**
     * Get data in chunks using a generator
     *
     * Uses chunkById pattern for better performance with large datasets.
     * This fetches records from the DATABASE in batches (not splitting a collection).
     *
     * @param Builder $query The Eloquent query builder
     * @param int $chunkSize Number of records to fetch per batch
     */
    protected function getChunkedData(Builder $query, int $chunkSize): Generator
    {
        $primaryKey = $query->getModel()->getKeyName();
        $table = $query->getModel()->getTable();
        $qualifiedKey = $table . '.' . $primaryKey;

        // Get the base query without any existing order/limit that might conflict
        // We need to reorder by primary key for chunking to work correctly
        $baseQuery = clone $query;

        // Remove existing orders and limits for chunking
        $baseQuery->reorder()->limit(null);

        $lastId = 0;
        $totalProcessed = 0;

        // Check if original query had a limit
        $originalLimit = $query->getQuery()->limit;

        do {
            $chunkQuery = (clone $baseQuery)
                ->where($qualifiedKey, '>', $lastId)
                ->orderBy($qualifiedKey)
                ->limit($chunkSize);

            $results = $chunkQuery->get();

            foreach ($results as $row) {
                // Respect original limit if set
                if ($originalLimit !== null && $totalProcessed >= $originalLimit) {
                    return;
                }

                yield $row;
                $lastId = $row->{$primaryKey};
                $totalProcessed++;
            }
        } while ($results->count() === $chunkSize && ($originalLimit === null || $totalProcessed < $originalLimit));
    }

    /**
     * Get headings from export class
     */
    protected function getHeadings(object $export): array
    {
        if ($export instanceof WithHeadings) {
            return $export->headings();
        }

        if ($export instanceof WithColumnDefinitions) {
            return $export->columns()->getHeaders();
        }

        return [];
    }

    /**
     * Create appropriate exporter based on concerns
     */
    protected function createExporter(object $export, string $writerType): object
    {
        $options = $this->buildOptions($export);

        return match ($writerType) {
            'csv' => new CsvExporter($options),
            'json' => new JsonExporter($options),
            'xlsx', 'excel' => $this->selectExcelExporter($export, $options),
            default => new ExcelExporter($options),
        };
    }

    /**
     * Select the appropriate Excel exporter based on concerns
     *
     * Auto-detection priority:
     * 1. Explicit exporter choice (UseHybridExporter, UseStyledOpenSpout, UseChunkedWriter)
     * 2. Smart detection based on implemented concerns and estimated data size
     *
     * Decision matrix:
     * - WithConditionalFormatting + Large data = HybridExporter (XML manipulation)
     * - WithStyles/WithColumnFormatting + Small data = PhpSpreadsheetExporter
     * - WithChunkReading + No advanced styles = StyledOpenSpoutExporter (most memory efficient)
     * - Default = ExcelExporter (OpenSpout)
     */
    protected function selectExcelExporter(object $export, array $options): object
    {
        // 1. Check for explicit exporter choice first
        if ($export instanceof UseHybridExporter && $export->useHybridExporter()) {
            return new HybridExporter($options);
        }

        if ($export instanceof UseStyledOpenSpout && $export->useStyledOpenSpout()) {
            return new StyledOpenSpoutExporter($options);
        }

        if ($export instanceof UseChunkedWriter && $export->useChunkedWriter()) {
            return new ChunkedPhpSpreadsheetExporter($options);
        }

        // 2. Smart auto-detection based on implemented concerns
        $hasConditionalFormatting = $export instanceof WithConditionalFormatting;
        $hasAdvancedStyles = $export instanceof WithStyles;
        $hasColumnFormatting = $export instanceof WithColumnFormatting;
        $hasEvents = $export instanceof WithEvents;
        $hasChunking = $export instanceof WithChunkReading;
        $hasFreezeRow = $export instanceof WithFreezeRow;
        $hasAutoFilter = $export instanceof WithAutoFilter;

        // Determine if this is likely a large dataset
        $isLargeDataset = $hasChunking; // If chunking is implemented, assume large data

        // Conditional formatting with large data = HybridExporter
        // (XML manipulation is memory efficient for conditional formatting)
        if ($hasConditionalFormatting && $isLargeDataset) {
            return new HybridExporter($options);
        }

        // Large dataset with freeze/autofilter but no heavy styles = HybridExporter
        if ($isLargeDataset && ($hasFreezeRow || $hasAutoFilter) && !$hasAdvancedStyles && !$hasColumnFormatting) {
            return new HybridExporter($options);
        }

        // Large dataset, basic styling only = StyledOpenSpoutExporter (most memory efficient)
        if ($isLargeDataset && !$hasAdvancedStyles && !$hasColumnFormatting && !$hasConditionalFormatting) {
            return new StyledOpenSpoutExporter($options);
        }

        // Has advanced PhpSpreadsheet features (styles, events, column formatting)
        // Use PhpSpreadsheet for small datasets only
        if ($hasAdvancedStyles || $hasColumnFormatting || $hasEvents) {
            // If also has chunking, use ChunkedPhpSpreadsheet
            if ($isLargeDataset) {
                return new ChunkedPhpSpreadsheetExporter($options);
            }
            return new PhpSpreadsheetExporter($options);
        }

        // Default to plain OpenSpout (fastest, most memory efficient)
        return new ExcelExporter($options);
    }

    /**
     * Build options array from export concerns
     */
    protected function buildOptions(object $export): array
    {
        $options = [
            'include_headers' => true,
        ];

        // Sheet title
        if ($export instanceof WithTitle) {
            $options['sheet_name'] = $export->title();
        }

        // Column definitions
        if ($export instanceof WithColumnDefinitions) {
            $columns = $export->columns();
            $options['column_collection'] = $columns;
            $options['column_config'] = $columns->toConfig();
        }

        // Column widths
        if ($export instanceof WithColumnWidths) {
            $options['column_widths'] = $export->columnWidths();
        }

        // Column formatting
        if ($export instanceof WithColumnFormatting) {
            $options['column_formats'] = $export->columnFormats();
        }

        // Report header
        if ($export instanceof WithReportHeader) {
            $options['report_header'] = $export->reportHeader();
        }

        // Totals
        if ($export instanceof WithTotals) {
            $options['show_totals'] = true;
            $options['total_columns'] = $export->totalColumns();
            $options['totals_label'] = $export->totalLabel();
        }

        // Auto size
        if ($export instanceof ShouldAutoSize) {
            $options['auto_size'] = true;
        }

        // Conditional coloring
        if ($export instanceof WithConditionalColoring) {
            $options['conditional_coloring'] = true;
        }

        // Freeze row
        if ($export instanceof WithFreezeRow) {
            $options['freeze_header'] = true;
            $options['freeze_pane'] = $export->freezePane();
        }

        // Auto filter
        if ($export instanceof WithAutoFilter) {
            $options['auto_filter'] = true;
            $options['auto_filter_range'] = $export->autoFilter();
        }

        // Conditional formatting (for HybridExporter)
        if ($export instanceof WithConditionalFormatting) {
            $options['conditional_formats'] = $export->conditionalFormats();
        }

        // Styles (for PhpSpreadsheet)
        if ($export instanceof WithStyles) {
            $options['styles_callback'] = fn($sheet) => $export->styles($sheet);
        }

        // Events
        if ($export instanceof WithEvents) {
            $options['events'] = $export->registerEvents();
        }

        // Multiple sheets
        if ($export instanceof WithMultipleSheets) {
            $options['sheets'] = $export->sheets();
        }

        return $options;
    }

    /**
     * Check if we should use PhpSpreadsheet for advanced features
     */
    protected function shouldUsePhpSpreadsheet(object $export): bool
    {
        return $export instanceof WithStyles
            || $export instanceof WithColumnFormatting
            || $export instanceof WithEvents;
    }

    /**
     * Detect writer type from filename
     */
    protected function detectWriterType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => 'csv',
            'json' => 'json',
            'xlsx', 'xls' => 'xlsx',
            default => 'xlsx',
        };
    }

    // ========================================
    // IMPORT METHODS
    // ========================================

    /**
     * Import a file using an import class
     *
     * @param object $import The import class instance
     * @param string $filePath Path to the file (or uploaded file path)
     * @param string|null $disk Storage disk (optional)
     * @param string|null $readerType Force reader type (csv, xlsx, json)
     */
    public function import(object $import, string $filePath, ?string $disk = null, ?string $readerType = null): ImportResult
    {
        $importer = new Importer();
        return $importer->import($import, $filePath, $disk, $readerType);
    }

    /**
     * Convert file to array
     */
    public function toArray(object $import, string $filePath, ?string $disk = null, ?string $readerType = null): array
    {
        $importer = new Importer();
        return $importer->toArray($import, $filePath, $disk, $readerType);
    }

    /**
     * Convert file to collection
     */
    public function toCollection(object $import, string $filePath, ?string $disk = null, ?string $readerType = null): Collection
    {
        $importer = new Importer();
        return $importer->toCollection($import, $filePath, $disk, $readerType);
    }

    /**
     * Queue import for background processing
     */
    public function queueImport(object $import, string $filePath, ?string $disk = null, ?string $readerType = null): ImportResult
    {
        // For now, process synchronously
        // Future: dispatch to queue
        return $this->import($import, $filePath, $disk, $readerType);
    }
}
