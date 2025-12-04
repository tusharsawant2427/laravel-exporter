<?php

namespace LaravelExporter;

use Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Model;
use LaravelExporter\Contracts\FormatReaderInterface;
use LaravelExporter\Concerns\ToCollection;
use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\ToArray;
use LaravelExporter\Concerns\OnEachRow;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Concerns\WithBatchInserts;
use LaravelExporter\Concerns\WithChunkReading;
use LaravelExporter\Concerns\WithValidation;
use LaravelExporter\Concerns\WithUpserts;
use LaravelExporter\Concerns\WithStartRow;
use LaravelExporter\Concerns\WithLimit;
use LaravelExporter\Concerns\WithColumnLimit;
use LaravelExporter\Concerns\WithMappedCells;
use LaravelExporter\Concerns\WithMultipleSheets;
use LaravelExporter\Concerns\WithCalculatedFormulas;
use LaravelExporter\Concerns\WithProgressBar;
use LaravelExporter\Concerns\SkipsOnError;
use LaravelExporter\Concerns\SkipsOnFailure;
use LaravelExporter\Imports\Row;
use LaravelExporter\Imports\Failure;
use LaravelExporter\Imports\ImportResult;
use LaravelExporter\Imports\ValidationException;
use LaravelExporter\Imports\HeadingRowFormatter;
use LaravelExporter\Readers\CsvReader;
use LaravelExporter\Readers\JsonReader;
use LaravelExporter\Readers\ExcelReader;
use LaravelExporter\Readers\PhpSpreadsheetReader;

/**
 * Main Importer Class - Handles file imports with Maatwebsite-style concerns
 *
 * Usage:
 *   $importer = new Importer();
 *   $importer->import(new UsersImport, 'users.xlsx');
 */
class Importer
{
    protected ?object $progressOutput = null;
    protected string $headingFormat = 'slug';

    /**
     * Import a file using an import class
     */
    public function import(object $import, string $filePath, ?string $disk = null, ?string $readerType = null): ImportResult
    {
        $startTime = microtime(true);
        $startMemory = memory_get_peak_usage(true);

        $result = new ImportResult();

        // Handle disk storage
        $originalPath = $filePath;
        if ($disk !== null) {
            $filePath = $this->resolveFromDisk($filePath, $disk);
        }

        // Detect reader type
        $readerType = $readerType ?? $this->detectReaderType($filePath);

        // JSON files have associative arrays, no heading row processing needed
        $isAssociativeFormat = $readerType === 'json';

        // Handle multiple sheets
        if ($import instanceof WithMultipleSheets) {
            return $this->importMultipleSheets($import, $filePath, $readerType);
        }

        // Handle mapped cells (specific cell reading)
        if ($import instanceof WithMappedCells) {
            return $this->importMappedCells($import, $filePath);
        }

        // Handle collection-based imports (ToCollection, ToArray without ToModel)
        if ($this->shouldProcessAsCollection($import)) {
            $this->processAsCollection($import, $filePath, null, $readerType, $result);
            $result->setDuration(microtime(true) - $startTime);
            $result->setPeakMemory(memory_get_peak_usage(true) - $startMemory);
            return $result;
        }

        // Create reader for row-by-row processing
        $reader = $this->createReader($readerType, $import);

        // Build options
        $options = $this->buildReaderOptions($import);

        // Get headings if applicable (not for JSON - it's already associative)
        $headings = null;
        if (!$isAssociativeFormat && $import instanceof WithHeadingRow) {
            $headings = $this->readHeadings($reader, $filePath, $import, $options);
        }

        // Get total count for progress
        if ($import instanceof WithProgressBar) {
            $totalRows = $reader->getRowCount($filePath);
            if ($totalRows !== null) {
                $result->setTotalRows($totalRows);
            }
        }

        // Process rows
        $this->processRows($import, $reader, $filePath, $options, $headings, $result, $isAssociativeFormat);

        // Calculate duration and memory
        $result->setDuration(microtime(true) - $startTime);
        $result->setPeakMemory(memory_get_peak_usage(true) - $startMemory);

        return $result;
    }

    /**
     * Convert file to array
     */
    public function toArray(object $import, string $filePath, ?string $disk = null, ?string $readerType = null): array
    {
        if ($disk !== null) {
            $filePath = $this->resolveFromDisk($filePath, $disk);
        }

        $readerType = $readerType ?? $this->detectReaderType($filePath);
        $reader = $this->createReader($readerType, $import);
        $options = $this->buildReaderOptions($import);

        // JSON files already have associative arrays, no heading row needed
        $isJson = $readerType === 'json';

        $headings = null;
        if (!$isJson && $import instanceof WithHeadingRow) {
            $headings = $this->readHeadings($reader, $filePath, $import, $options);
        }

        $rows = [];
        foreach ($reader->read($filePath, $options) as $rowNumber => $rowData) {
            // Skip heading row for non-JSON files
            if (!$isJson && $headings && $rowNumber === ($import instanceof WithHeadingRow ? $import->headingRow() : 1)) {
                continue;
            }

            // Apply headings for non-JSON files
            if (!$isJson && $headings) {
                $rowData = array_combine(
                    array_pad($headings, count($rowData), 'column_' . count($headings)),
                    array_pad($rowData, count($headings), null)
                );
            }

            $rows[] = $rowData;
        }

        return $rows;
    }

    /**
     * Convert file to collection
     */
    public function toCollection(object $import, string $filePath, ?string $disk = null, ?string $readerType = null): Collection
    {
        return new Collection($this->toArray($import, $filePath, $disk, $readerType));
    }

    /**
     * Set progress output (for console commands)
     */
    public function withProgressOutput(object $output): self
    {
        $this->progressOutput = $output;
        return $this;
    }

    /**
     * Set heading format
     */
    public function setHeadingFormat(string $format): self
    {
        $this->headingFormat = $format;
        return $this;
    }

    /**
     * Process rows from the reader
     */
    protected function processRows(
        object $import,
        FormatReaderInterface $reader,
        string $filePath,
        array $options,
        ?array $headings,
        ImportResult $result,
        bool $isAssociativeFormat = false
    ): void {
        $batchSize = $import instanceof WithBatchInserts ? $import->batchSize() : 1;
        $headingRow = $import instanceof WithHeadingRow ? $import->headingRow() : 1;
        $uniqueBy = $import instanceof WithUpserts ? $import->uniqueBy() : null;

        $batch = [];
        $failures = [];
        $rowCount = 0;

        foreach ($reader->read($filePath, $options) as $rowNumber => $rowData) {
            // Skip heading row (only for non-associative formats)
            if (!$isAssociativeFormat && $headings && $rowNumber === $headingRow) {
                continue;
            }

            $rowCount++;

            // Apply headings (only for non-associative formats)
            if (!$isAssociativeFormat && $headings) {
                $rowData = array_combine(
                    array_pad($headings, count($rowData), 'column_' . count($headings)),
                    array_pad($rowData, count($headings), null)
                );
            }

            // Create Row object
            $row = new Row($rowNumber, $rowData, $headings);

            // Set row number if trait is used
            if (method_exists($import, 'setRowNumber')) {
                $import->setRowNumber($rowNumber);
            }

            try {
                // Validate if applicable
                if ($import instanceof WithValidation) {
                    $rowFailures = $this->validateRow($import, $rowData, $rowNumber);
                    if (!empty($rowFailures)) {
                        if ($import instanceof SkipsOnFailure) {
                            $import->onFailure(...$rowFailures);
                            foreach ($rowFailures as $failure) {
                                $result->errors()->addFailure($failure);
                            }
                            $result->incrementSkipped();
                            continue;
                        }
                        throw new ValidationException($rowFailures);
                    }
                }

                // Process based on import type
                if ($import instanceof OnEachRow) {
                    $import->onRow($row);
                    $result->incrementImported();
                } elseif ($import instanceof ToModel) {
                    $models = $import->model($rowData);

                    if ($models === null) {
                        $result->incrementSkipped();
                        continue;
                    }

                    $models = is_array($models) ? $models : [$models];

                    foreach ($models as $model) {
                        if ($batchSize > 1) {
                            $batch[] = $model;

                            if (count($batch) >= $batchSize) {
                                $this->saveBatch($batch, $uniqueBy);
                                $result->incrementImported(count($batch));
                                $batch = [];
                            }
                        } else {
                            if ($uniqueBy) {
                                $this->upsertModel($model, $uniqueBy);
                            } else {
                                $model->save();
                            }
                            $result->incrementImported();
                        }
                    }
                }

                // Progress callback
                if ($this->progressOutput && $import instanceof WithProgressBar) {
                    $this->advanceProgress();
                }

            } catch (\Throwable $e) {
                if ($import instanceof SkipsOnError) {
                    $import->onError($e);
                    $result->errors()->addError($rowNumber, $e);
                    $result->incrementSkipped();
                } else {
                    throw $e;
                }
            }
        }

        // Save remaining batch
        if (!empty($batch)) {
            $this->saveBatch($batch, $uniqueBy);
            $result->incrementImported(count($batch));
        }

        $result->setTotalRows($rowCount);
    }

    /**
     * Check if import should use collection-based processing
     * (ToCollection and ToArray skip row-by-row processing)
     */
    protected function shouldProcessAsCollection(object $import): bool
    {
        return ($import instanceof ToCollection || $import instanceof ToArray)
            && !($import instanceof ToModel)
            && !($import instanceof OnEachRow);
    }

    /**
     * Process collection-based imports
     */
    protected function processAsCollection(
        object $import,
        string $filePath,
        ?string $disk,
        ?string $readerType,
        ImportResult $result
    ): void {
        if ($import instanceof ToCollection) {
            $collection = $this->toCollection($import, $filePath, $disk, $readerType);
            $import->collection($collection);
            $result->setTotalRows($collection->count());
            $result->incrementImported($collection->count());
        } elseif ($import instanceof ToArray) {
            $array = $this->toArray($import, $filePath, $disk, $readerType);
            $import->array($array);
            $result->setTotalRows(count($array));
            $result->incrementImported(count($array));
        }
    }

    /**
     * Read headings from file
     */
    protected function readHeadings(
        FormatReaderInterface $reader,
        string $filePath,
        object $import,
        array $options
    ): array {
        $headingRow = $import instanceof WithHeadingRow ? $import->headingRow() : 1;

        // Read just the heading row
        $tempOptions = array_merge($options, [
            'start_row' => $headingRow,
            'limit' => 1,
        ]);

        foreach ($reader->read($filePath, $tempOptions) as $row) {
            return HeadingRowFormatter::format($row, $this->headingFormat);
        }

        return [];
    }

    /**
     * Validate a single row
     *
     * @return Failure[]
     */
    protected function validateRow(WithValidation $import, array $row, int $rowNumber): array
    {
        $rules = $import->rules();
        $messages = method_exists($import, 'customValidationMessages')
            ? $import->customValidationMessages()
            : [];
        $attributes = method_exists($import, 'customValidationAttributes')
            ? $import->customValidationAttributes()
            : [];

        $validator = Validator::make($row, $rules, $messages, $attributes);

        if ($validator->fails()) {
            $failures = [];
            foreach ($validator->errors()->toArray() as $attribute => $errors) {
                $failures[] = new Failure($rowNumber, $attribute, $errors, $row);
            }
            return $failures;
        }

        return [];
    }

    /**
     * Save a batch of models
     */
    protected function saveBatch(array $models, ?string $uniqueBy): void
    {
        if (empty($models)) {
            return;
        }

        $firstModel = $models[0];
        $class = get_class($firstModel);

        if ($uniqueBy) {
            // Use upsert for batch
            $data = array_map(fn($m) => $m->getAttributes(), $models);
            $uniqueColumns = is_array($uniqueBy) ? $uniqueBy : [$uniqueBy];

            $class::upsert($data, $uniqueColumns, array_keys($data[0]));
        } else {
            // Use insert for batch
            $data = array_map(function ($model) {
                $attributes = $model->getAttributes();
                if ($model->usesTimestamps()) {
                    $now = now();
                    $attributes['created_at'] = $attributes['created_at'] ?? $now;
                    $attributes['updated_at'] = $attributes['updated_at'] ?? $now;
                }
                return $attributes;
            }, $models);

            $class::insert($data);
        }
    }

    /**
     * Upsert a single model
     */
    protected function upsertModel(Model $model, string|array $uniqueBy): void
    {
        $uniqueColumns = is_array($uniqueBy) ? $uniqueBy : [$uniqueBy];
        $class = get_class($model);

        $class::upsert(
            [$model->getAttributes()],
            $uniqueColumns,
            array_keys($model->getAttributes())
        );
    }

    /**
     * Import multiple sheets
     */
    protected function importMultipleSheets(
        WithMultipleSheets $import,
        string $filePath,
        string $readerType
    ): ImportResult {
        $result = new ImportResult();
        $sheets = $import->sheets();

        foreach ($sheets as $sheetKey => $sheetImport) {
            $sheetResult = $this->import($sheetImport, $filePath, null, $readerType);

            // Aggregate results
            $result->incrementImported($sheetResult->importedRows());
            $result->incrementSkipped($sheetResult->skippedRows());
            $result->incrementFailed($sheetResult->failedRows());
        }

        return $result;
    }

    /**
     * Import mapped cells
     */
    protected function importMappedCells(WithMappedCells $import, string $filePath): ImportResult
    {
        $reader = new PhpSpreadsheetReader();
        $data = $reader->readCells($filePath, $import->mapping());

        // Call the collection method with mapped data
        if ($import instanceof ToCollection) {
            $import->collection(new Collection([$data]));
        } elseif ($import instanceof ToArray) {
            $import->array([$data]);
        }

        $result = new ImportResult();
        $result->setTotalRows(1);
        $result->incrementImported();

        return $result;
    }

    /**
     * Build reader options from import class
     */
    protected function buildReaderOptions(object $import): array
    {
        $options = [];

        if ($import instanceof WithStartRow) {
            $options['start_row'] = $import->startRow();
        }

        if ($import instanceof WithLimit) {
            $options['limit'] = $import->limit();
        }

        if ($import instanceof WithColumnLimit) {
            $options['end_column'] = $import->endColumn();
        }

        if ($import instanceof WithCalculatedFormulas) {
            $options['calculate_formulas'] = true;
        }

        return $options;
    }

    /**
     * Create appropriate reader for file type
     */
    protected function createReader(string $type, object $import): FormatReaderInterface
    {
        // For formula calculation, prefer PhpSpreadsheet
        if ($import instanceof WithCalculatedFormulas && in_array($type, ['xlsx', 'xls'])) {
            return new PhpSpreadsheetReader();
        }

        return match ($type) {
            'csv', 'txt', 'tsv' => new CsvReader(),
            'json' => new JsonReader(),
            'xlsx', 'xls' => new ExcelReader(),
            default => throw new \InvalidArgumentException("Unsupported file type: {$type}"),
        };
    }

    /**
     * Detect reader type from filename
     */
    protected function detectReaderType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv', 'txt', 'tsv' => $extension,
            'xlsx', 'xls', 'xlsm' => 'xlsx',
            'json' => 'json',
            default => throw new \InvalidArgumentException("Cannot detect file type: {$extension}"),
        };
    }

    /**
     * Resolve file path from disk storage
     */
    protected function resolveFromDisk(string $path, string $disk): string
    {
        $storage = \Illuminate\Support\Facades\Storage::disk($disk);

        if (!$storage->exists($path)) {
            throw new \RuntimeException("File not found on disk '{$disk}': {$path}");
        }

        // Copy to temp file
        $tempPath = sys_get_temp_dir() . '/' . uniqid('import_') . '_' . basename($path);
        file_put_contents($tempPath, $storage->get($path));

        return $tempPath;
    }

    /**
     * Advance progress bar
     */
    protected function advanceProgress(): void
    {
        if ($this->progressOutput && method_exists($this->progressOutput, 'advance')) {
            $this->progressOutput->advance();
        }
    }
}
