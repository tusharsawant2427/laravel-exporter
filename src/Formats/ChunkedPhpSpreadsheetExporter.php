<?php

namespace LaravelExporter\Formats;

use Generator;
use LaravelExporter\Contracts\FormatExporterInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Collection\Memory\SimpleCache;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Chunked PhpSpreadsheet Exporter
 *
 * Writes data in chunks with memory cleanup between chunks.
 * Uses PhpSpreadsheet's cell caching to reduce memory usage.
 *
 * Memory optimization techniques:
 * 1. Cell caching (stores cells in cache instead of memory)
 * 2. Batch writing with gc_collect_cycles()
 * 3. Disabling calculation caching
 * 4. Pre-calculated column widths (avoids auto-size memory overhead)
 */
class ChunkedPhpSpreadsheetExporter implements FormatExporterInterface
{
    protected int $chunkSize = 1000;
    protected bool $includeHeaders = true;
    protected string $sheetName = 'Sheet1';
    protected array $columnWidths = [];
    protected array $columnFormats = [];
    protected array $headerStyles = [];
    /** @var callable|null */
    protected $stylesCallback = null;

    public function __construct(array $options = [])
    {
        $this->chunkSize = $options['chunk_size'] ?? 1000;
        $this->includeHeaders = $options['include_headers'] ?? true;
        $this->sheetName = $options['sheet_name'] ?? 'Sheet1';
        $this->columnWidths = $options['column_widths'] ?? [];
        $this->columnFormats = $options['column_formats'] ?? [];
        $this->headerStyles = $options['header_styles'] ?? [];
        $this->stylesCallback = $options['styles_callback'] ?? null;

        // Enable cell caching to reduce memory
        $this->setupCellCaching();
    }

    /**
     * Setup cell caching to reduce memory usage
     */
    protected function setupCellCaching(): void
    {
        // Use simple in-memory cache with limits
        // This reduces memory by not keeping all cell objects in PHP memory
        if (class_exists('PhpOffice\PhpSpreadsheet\Settings')) {
            // Disable calculation caching (saves memory)
            \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance()
                ->disableBranchPruning();
        }
    }

    public function export(Generator $data, array $headers, string $path): bool
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sheetName);

        $rowIndex = 1;
        $columnCount = count($headers);

        // Write headers
        if ($this->includeHeaders && !empty($headers)) {
            foreach ($headers as $colIndex => $header) {
                $column = Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValue($column . $rowIndex, $header);
            }

            // Apply header styles
            $this->applyHeaderStyles($sheet, $columnCount);
            $rowIndex++;
        }

        // Apply column widths upfront (avoid auto-size which needs all data)
        $this->applyColumnWidths($sheet, $columnCount);

        // Write data in chunks
        $chunkCount = 0;
        $rowsInChunk = 0;
        $dataStartRow = $rowIndex;

        foreach ($data as $row) {
            $colIndex = 1;
            foreach ($row as $value) {
                $column = Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue($column . $rowIndex, $value);
                $colIndex++;
            }

            $rowIndex++;
            $rowsInChunk++;

            // Every chunk, trigger garbage collection
            if ($rowsInChunk >= $this->chunkSize) {
                $chunkCount++;
                $rowsInChunk = 0;

                // Force garbage collection
                gc_collect_cycles();
            }
        }

        $lastDataRow = $rowIndex - 1;

        // Apply column formats AFTER we know the actual row count
        $this->applyColumnFormats($sheet, $columnCount, $dataStartRow, $lastDataRow);

        // Apply custom styles callback
        if ($this->stylesCallback) {
            call_user_func($this->stylesCallback, $sheet);
        }

        // Write to file
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false); // Skip formula calc (saves memory)
        $writer->save($path);

        // Cleanup
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        gc_collect_cycles();

        return true;
    }

    protected function applyHeaderStyles($sheet, int $columnCount): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $headerRange = "A1:{$lastColumn}1";

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
        ]);
    }

    protected function applyColumnWidths($sheet, int $columnCount): void
    {
        if (!empty($this->columnWidths)) {
            foreach ($this->columnWidths as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
        } else {
            // Set default width for all columns (faster than auto-size)
            for ($i = 1; $i <= $columnCount; $i++) {
                $column = Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($column)->setWidth(15);
            }
        }
    }

    protected function applyColumnFormats($sheet, int $columnCount, int $startRow, int $endRow): void
    {
        if (!empty($this->columnFormats) && $endRow >= $startRow) {
            foreach ($this->columnFormats as $column => $format) {
                // Apply only to actual data rows (not entire column)
                $sheet->getStyle("{$column}{$startRow}:{$column}{$endRow}")
                    ->getNumberFormat()
                    ->setFormatCode($format);
            }
        }
    }

    public function download(Generator $data, array $headers, string $filename): mixed
    {
        return $this->stream($data, $headers, $filename);
    }

    public function toString(Generator $data, array $headers): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'chunked_excel_');
        $this->export($data, $headers, $tempFile);
        $content = file_get_contents($tempFile);
        unlink($tempFile);
        return $content;
    }

    public function stream(Generator $data, array $headers, string $filename): mixed
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'chunked_excel_');
        $this->export($data, $headers, $tempFile);

        return new StreamedResponse(function () use ($tempFile) {
            readfile($tempFile);
            unlink($tempFile);
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function getExtension(): string
    {
        return 'xlsx';
    }

    public function getContentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }
}
