<?php

namespace LaravelExporter\Formats;

use Generator;
use LaravelExporter\Contracts\FormatExporterInterface;
use LaravelExporter\ColumnTypes;
use LaravelExporter\Support\ColumnCollection;
use LaravelExporter\Support\ReportHeader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Advanced Excel Exporter using PhpSpreadsheet
 *
 * Provides advanced Excel features:
 * - Excel Formulas (SUM, AVERAGE, etc.)
 * - Dynamic Conditional Formatting
 * - Cell Merging
 * - Freeze Panes
 * - Auto Filter
 * - True Auto-Size Columns
 * - Direct Cell Access
 */
class PhpSpreadsheetExporter implements FormatExporterInterface
{
    protected bool $includeHeaders = true;
    protected string $sheetName = 'Sheet1';
    protected array $columnWidths = [];
    protected string $locale = 'en_IN';

    // Column configuration
    protected ?ColumnCollection $columnCollection = null;
    protected array $columnConfig = [];

    // Report header
    protected ?ReportHeader $reportHeader = null;

    // Conditional coloring
    protected bool $conditionalColoring = true;
    protected bool $dynamicConditionalFormatting = true;

    // Excel features
    protected bool $freezeHeader = true;
    protected bool $autoFilter = true;
    protected bool $autoSize = true;

    // Totals
    protected bool $showTotals = false;
    protected array $totalColumns = [];
    protected string $totalsLabel = 'TOTAL';
    protected bool $useFormulas = true;

    // Cell merging
    protected array $mergedCells = [];

    // Custom formulas
    protected array $customFormulas = [];

    // Multiple sheets
    protected array $sheets = [];

    public function __construct(array $options = [])
    {
        $this->includeHeaders = $options['include_headers'] ?? true;
        $this->sheetName = $options['sheet_name'] ?? 'Sheet1';
        $this->columnWidths = $options['column_widths'] ?? [];
        $this->locale = $options['locale'] ?? 'en_IN';
        $this->conditionalColoring = $options['conditional_coloring'] ?? true;
        $this->dynamicConditionalFormatting = $options['dynamic_conditional_formatting'] ?? true;
        $this->freezeHeader = $options['freeze_header'] ?? true;
        $this->autoFilter = $options['auto_filter'] ?? true;
        $this->autoSize = $options['auto_size'] ?? true;
        $this->showTotals = $options['show_totals'] ?? false;
        $this->totalColumns = $options['total_columns'] ?? [];
        $this->totalsLabel = $options['totals_label'] ?? 'TOTAL';
        $this->useFormulas = $options['use_formulas'] ?? true;
        $this->columnConfig = $options['column_config'] ?? [];
        $this->columnCollection = $options['column_collection'] ?? null;
        $this->reportHeader = $options['report_header'] ?? null;
        $this->mergedCells = $options['merged_cells'] ?? [];
        $this->customFormulas = $options['custom_formulas'] ?? [];
        $this->sheets = $options['sheets'] ?? [];
    }

    public function export(Generator $data, array $headers, string $path): bool
    {
        $spreadsheet = $this->createSpreadsheet($data, $headers);

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        // Clean up
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return true;
    }

    public function download(Generator $data, array $headers, string $filename): mixed
    {
        return $this->stream($data, $headers, $filename);
    }

    public function toString(Generator $data, array $headers): string
    {
        $spreadsheet = $this->createSpreadsheet($data, $headers);

        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $content;
    }

    public function stream(Generator $data, array $headers, string $filename): mixed
    {
        $spreadsheet = $this->createSpreadsheet($data, $headers);

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Create the spreadsheet with all features
     */
    protected function createSpreadsheet(Generator $data, array $headers): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        // Handle multiple sheets
        if (!empty($this->sheets)) {
            $this->createMultipleSheets($spreadsheet);
        } else {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($this->sheetName);
            $this->populateSheet($sheet, $data, $headers);
        }

        return $spreadsheet;
    }

    /**
     * Create multiple sheets
     */
    protected function createMultipleSheets(Spreadsheet $spreadsheet): void
    {
        $isFirst = true;

        foreach ($this->sheets as $sheetConfig) {
            if ($isFirst) {
                $sheet = $spreadsheet->getActiveSheet();
                $isFirst = false;
            } else {
                $sheet = $spreadsheet->createSheet();
            }

            $sheet->setTitle($sheetConfig->getName());

            // Get sheet-specific configuration
            $sheetData = $this->getSheetDataAsArray($sheetConfig);
            $sheetHeaders = $sheetConfig->getHeaders();

            // Temporarily set sheet config
            $originalConfig = $this->columnConfig;
            $originalReportHeader = $this->reportHeader;
            $originalShowTotals = $this->showTotals;
            $originalTotalColumns = $this->totalColumns;

            $this->columnConfig = $sheetConfig->getColumnConfig();
            $this->reportHeader = $sheetConfig->getReportHeader();
            $this->showTotals = $sheetConfig->hasTotals();
            $this->totalColumns = $sheetConfig->getTotalColumns();

            $this->populateSheetFromArray($sheet, $sheetData, $sheetHeaders);

            // Restore config
            $this->columnConfig = $originalConfig;
            $this->reportHeader = $originalReportHeader;
            $this->showTotals = $originalShowTotals;
            $this->totalColumns = $originalTotalColumns;
        }
    }

    /**
     * Get sheet data as array
     */
    protected function getSheetDataAsArray($sheet): array
    {
        $data = $sheet->getData();
        $result = [];

        if (is_iterable($data)) {
            foreach ($data as $item) {
                $result[] = $this->toArray($item);
            }
        }

        return $result;
    }

    /**
     * Populate sheet from array data
     */
    protected function populateSheetFromArray(Worksheet $sheet, array $data, array $headers): void
    {
        $generator = (function () use ($data) {
            foreach ($data as $row) {
                yield $row;
            }
        })();

        $this->populateSheet($sheet, $generator, $headers);
    }

    /**
     * Populate a single sheet with data
     */
    protected function populateSheet(Worksheet $sheet, Generator $data, array $headers): void
    {
        $currentRow = 1;
        $columnCount = count($headers);
        $dataStartRow = 1;

        // Write report header if present
        if ($this->reportHeader) {
            $currentRow = $this->writeReportHeader($sheet, $columnCount);
            $dataStartRow = $currentRow;
        }

        // Write column headers
        if ($this->includeHeaders && !empty($headers)) {
            $this->writeHeaders($sheet, $headers, $currentRow);
            $dataStartRow = $currentRow + 1;
            $currentRow++;
        }

        // Write data rows
        $dataRows = [];
        foreach ($data as $row) {
            $values = array_values($row);
            $dataRows[] = $row;

            foreach ($values as $colIndex => $value) {
                $col = Coordinate::stringFromColumnIndex($colIndex + 1);
                $cell = $sheet->getCell("{$col}{$currentRow}");
                $this->setCellValue($cell, $value, $colIndex);
            }
            $currentRow++;
        }

        $lastDataRow = $currentRow - 1;

        // Write totals row with formulas
        if ($this->showTotals && !empty($this->totalColumns)) {
            $this->writeTotalsRow($sheet, $headers, $dataStartRow, $lastDataRow, $currentRow);
            $currentRow++;
        }

        // Apply custom formulas
        foreach ($this->customFormulas as $formula) {
            $sheet->setCellValue($formula['cell'], $formula['formula']);
        }

        // Apply merged cells
        foreach ($this->mergedCells as $range) {
            $sheet->mergeCells($range);
        }

        // Apply dynamic conditional formatting
        if ($this->conditionalColoring && $this->dynamicConditionalFormatting) {
            $this->applyDynamicConditionalFormatting($sheet, $headers, $dataStartRow, $lastDataRow);
        }

        // Apply Excel features
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);

        if ($this->freezeHeader) {
            $sheet->freezePane('A' . $dataStartRow);
        }

        if ($this->autoFilter) {
            $headerRow = $dataStartRow - 1;
            $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}{$headerRow}");
        }

        if ($this->autoSize) {
            foreach (range(1, $columnCount) as $colIndex) {
                $sheet->getColumnDimensionByColumn($colIndex)->setAutoSize(true);
            }
        }

        // Apply number formats
        $this->applyNumberFormats($sheet, $headers, $dataStartRow, $lastDataRow);
    }

    /**
     * Write report header with merged cells
     */
    protected function writeReportHeader(Worksheet $sheet, int $columnCount): int
    {
        $row = 1;
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);

        foreach ($this->reportHeader->getRows() as $headerRow) {
            $text = $headerRow['text'];
            $style = $headerRow['style'];

            // Merge cells across all columns
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
            $sheet->setCellValue("A{$row}", $text);

            // Apply style based on type
            $cellStyle = $sheet->getStyle("A{$row}");
            $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            switch ($style) {
                case 'company':
                case 'title':
                    $cellStyle->getFont()->setBold(true)->setSize(14);
                    break;
                case 'subtitle':
                    $cellStyle->getFont()->setBold(true)->setSize(12);
                    break;
                default:
                    $cellStyle->getFont()->setSize(10);
            }

            $row++;
        }

        // Empty row after header
        $row++;

        return $row;
    }

    /**
     * Write column headers with styling
     */
    protected function writeHeaders(Worksheet $sheet, array $headers, int $row): void
    {
        foreach ($headers as $colIndex => $header) {
            $col = Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue("{$col}{$row}", $header);
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $range = "A{$row}:{$lastColumn}{$row}";

        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);
    }

    /**
     * Set cell value with proper type handling
     */
    protected function setCellValue($cell, $value, int $colIndex): void
    {
        $columnKeys = array_keys($this->columnConfig);
        $key = $columnKeys[$colIndex] ?? null;
        $type = $this->columnConfig[$key]['type'] ?? ColumnTypes::STRING;

        if ($value === null || $value === '') {
            $cell->setValue('');
            return;
        }

        switch ($type) {
            case ColumnTypes::AMOUNT:
            case ColumnTypes::AMOUNT_PLAIN:
            case ColumnTypes::INTEGER:
            case ColumnTypes::QUANTITY:
            case ColumnTypes::PERCENTAGE:
                $cell->setValue((float) $value);
                break;
            case ColumnTypes::DATE:
            case ColumnTypes::DATETIME:
                if ($value instanceof \DateTimeInterface) {
                    $cell->setValue(\PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($value));
                } else {
                    $cell->setValue($value);
                }
                break;
            case ColumnTypes::BOOLEAN:
                $cell->setValue($value ? 'Yes' : 'No');
                break;
            default:
                $cell->setValue((string) $value);
        }
    }

    /**
     * Write totals row with Excel formulas
     */
    protected function writeTotalsRow(Worksheet $sheet, array $headers, int $startRow, int $endRow, int $totalRow): void
    {
        $columnKeys = array_keys($this->columnConfig);

        // Write label in first column
        $sheet->setCellValue("A{$totalRow}", $this->totalsLabel);

        foreach ($headers as $colIndex => $header) {
            $key = $columnKeys[$colIndex] ?? $header;
            $column = Coordinate::stringFromColumnIndex($colIndex + 1);

            if (in_array($key, $this->totalColumns) || in_array($header, $this->totalColumns)) {
                if ($this->useFormulas) {
                    // Use SUM formula
                    $formula = "=SUM({$column}{$startRow}:{$column}{$endRow})";
                    $sheet->setCellValue("{$column}{$totalRow}", $formula);
                } else {
                    // Calculate value
                    $sum = 0;
                    for ($row = $startRow; $row <= $endRow; $row++) {
                        $sum += (float) $sheet->getCell("{$column}{$row}")->getValue();
                    }
                    $sheet->setCellValue("{$column}{$totalRow}", $sum);
                }
            }
        }

        // Style totals row
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $range = "A{$totalRow}:{$lastColumn}{$totalRow}";

        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2EFDA'],
            ],
            'borders' => [
                'top' => [
                    'borderStyle' => Border::BORDER_DOUBLE,
                    'color' => ['rgb' => '000000'],
                ],
                'bottom' => [
                    'borderStyle' => Border::BORDER_DOUBLE,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);
    }

    /**
     * Apply dynamic conditional formatting (Excel-native)
     */
    protected function applyDynamicConditionalFormatting(Worksheet $sheet, array $headers, int $startRow, int $endRow): void
    {
        $columnKeys = array_keys($this->columnConfig);

        foreach ($headers as $colIndex => $header) {
            $key = $columnKeys[$colIndex] ?? null;

            if (!$key || !isset($this->columnConfig[$key])) {
                continue;
            }

            $config = $this->columnConfig[$key];
            $type = $config['type'] ?? ColumnTypes::STRING;
            $colorConditional = $config['color_conditional'] ?? false;

            if (!$colorConditional || !in_array($type, [ColumnTypes::AMOUNT, ColumnTypes::AMOUNT_PLAIN])) {
                continue;
            }

            $column = Coordinate::stringFromColumnIndex($colIndex + 1);
            $range = "{$column}{$startRow}:{$column}{$endRow}";

            // Positive values - Green
            $conditionalPositive = new Conditional();
            $conditionalPositive->setConditionType(Conditional::CONDITION_CELLIS);
            $conditionalPositive->setOperatorType(Conditional::OPERATOR_GREATERTHAN);
            $conditionalPositive->addCondition('0');
            $conditionalPositive->getStyle()->getFont()->getColor()->setARGB('FF006400');

            // Negative values - Red
            $conditionalNegative = new Conditional();
            $conditionalNegative->setConditionType(Conditional::CONDITION_CELLIS);
            $conditionalNegative->setOperatorType(Conditional::OPERATOR_LESSTHAN);
            $conditionalNegative->addCondition('0');
            $conditionalNegative->getStyle()->getFont()->getColor()->setARGB('FFCC0000');

            $sheet->getStyle($range)->setConditionalStyles([$conditionalPositive, $conditionalNegative]);
        }
    }

    /**
     * Apply number formats to columns
     */
    protected function applyNumberFormats(Worksheet $sheet, array $headers, int $startRow, int $endRow): void
    {
        $columnKeys = array_keys($this->columnConfig);

        foreach ($headers as $colIndex => $header) {
            $key = $columnKeys[$colIndex] ?? null;

            if (!$key || !isset($this->columnConfig[$key])) {
                continue;
            }

            $type = $this->columnConfig[$key]['type'] ?? ColumnTypes::STRING;
            $column = Coordinate::stringFromColumnIndex($colIndex + 1);
            $range = "{$column}{$startRow}:{$column}{$endRow}";

            // Include totals row if present
            if ($this->showTotals) {
                $range = "{$column}{$startRow}:{$column}" . ($endRow + 1);
            }

            $format = match ($type) {
                ColumnTypes::AMOUNT, ColumnTypes::AMOUNT_PLAIN => '#,##,##0.00',
                ColumnTypes::INTEGER => '#,##0',
                ColumnTypes::QUANTITY => '#,##,##0.00',
                ColumnTypes::PERCENTAGE => '0.00%',
                ColumnTypes::DATE => 'DD-MMM-YYYY',
                ColumnTypes::DATETIME => 'DD-MMM-YYYY HH:MM:SS',
                default => NumberFormat::FORMAT_GENERAL,
            };

            $sheet->getStyle($range)->getNumberFormat()->setFormatCode($format);
        }
    }

    /**
     * Convert item to array
     */
    protected function toArray(mixed $item): array
    {
        if (is_array($item)) {
            return $item;
        }

        if (is_object($item) && method_exists($item, 'toArray')) {
            return $item->toArray();
        }

        if (is_object($item)) {
            return get_object_vars($item);
        }

        return [$item];
    }

    /**
     * Get the file extension for this format
     */
    public function getExtension(): string
    {
        return 'xlsx';
    }

    /**
     * Get the content type for this format
     */
    public function getContentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }
}
