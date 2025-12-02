<?php

namespace LaravelExporter\Formats;

use Generator;
use LaravelExporter\Contracts\FormatExporterInterface;
use LaravelExporter\ColumnTypes;
use LaravelExporter\Styling\ExcelStyleBuilder;
use LaravelExporter\Support\ColumnCollection;
use LaravelExporter\Support\ReportHeader;
use LaravelExporter\Support\Sheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel Exporter using native PHP (no external dependencies)
 * Creates Excel-compatible XML (SpreadsheetML) format
 *
 * For better XLSX support, install: composer require openspout/openspout
 *
 * Supports:
 * - Column type formatting (amount, date, percentage, etc.)
 * - Conditional coloring for amounts (green/red)
 * - Report headers (company, title, date range)
 * - Totals row
 * - INR locale formatting
 * - Multiple sheets
 */
class ExcelExporter implements FormatExporterInterface
{
    protected bool $includeHeaders = true;
    protected string $sheetName = 'Sheet1';
    protected array $columnWidths = [];
    protected bool $useOpenSpout = false;
    protected string $locale = 'en_IN';

    // Enhanced styling options
    protected ?ExcelStyleBuilder $styleBuilder = null;
    protected ?ColumnCollection $columnCollection = null;
    protected ?ReportHeader $reportHeader = null;
    protected array $columnConfig = [];
    protected bool $conditionalColoring = true;
    protected bool $freezeHeader = true;
    protected bool $autoFilter = true;

    // Totals
    protected bool $showTotals = false;
    protected array $totalColumns = [];
    protected string $totalsLabel = 'TOTAL';

    // Multiple sheets
    /** @var array<string, Sheet> */
    protected array $sheets = [];

    public function __construct(array $options = [])
    {
        $this->includeHeaders = $options['include_headers'] ?? $this->includeHeaders;
        $this->sheetName = $options['sheet_name'] ?? $this->sheetName;
        $this->columnWidths = $options['column_widths'] ?? $this->columnWidths;
        $this->locale = $options['locale'] ?? $this->locale;
        $this->conditionalColoring = $options['conditional_coloring'] ?? $this->conditionalColoring;
        $this->freezeHeader = $options['freeze_header'] ?? $this->freezeHeader;
        $this->autoFilter = $options['auto_filter'] ?? $this->autoFilter;

        // Column configuration
        $this->columnConfig = $options['column_config'] ?? [];
        $this->columnCollection = $options['column_collection'] ?? null;

        // Report header
        $this->reportHeader = $options['report_header'] ?? null;

        // Styling
        $this->styleBuilder = $options['style_builder'] ?? null;

        // Totals
        $this->showTotals = $options['show_totals'] ?? false;
        $this->totalColumns = $options['total_columns'] ?? [];
        $this->totalsLabel = $options['totals_label'] ?? 'TOTAL';

        // Multiple sheets
        $this->sheets = $options['sheets'] ?? [];

        // Check if OpenSpout is available for better XLSX support
        $this->useOpenSpout = class_exists('OpenSpout\Writer\XLSX\Writer');
    }

    /**
     * Check if multiple sheets are configured
     */
    protected function hasMultipleSheets(): bool
    {
        return count($this->sheets) > 0;
    }

    public function export(Generator $data, array $headers, string $path): bool
    {
        // Handle multiple sheets
        if ($this->hasMultipleSheets()) {
            return $this->exportMultipleSheets($path);
        }

        if ($this->useOpenSpout) {
            return $this->exportWithOpenSpout($data, $headers, $path);
        }

        return $this->exportAsXml($data, $headers, $path);
    }

    /**
     * Export multiple sheets to file
     */
    protected function exportMultipleSheets(string $path): bool
    {
        if ($this->useOpenSpout) {
            return $this->exportMultipleSheetsWithOpenSpout($path);
        }

        return $this->exportMultipleSheetsAsXml($path);
    }

    /**
     * Export multiple sheets using OpenSpout
     */
    protected function exportMultipleSheetsWithOpenSpout(string $path): bool
    {
        $writerClass = 'OpenSpout\Writer\XLSX\Writer';
        $rowClass = 'OpenSpout\Common\Entity\Row';
        $cellClass = 'OpenSpout\Common\Entity\Cell';
        $styleClass = 'OpenSpout\Common\Entity\Style\Style';

        $writer = new $writerClass();
        $writer->openToFile($path);

        // Prepare reusable styles
        $headerStyle = new $styleClass();
        $headerStyle->setFontBold();
        $headerStyle->setBackgroundColor('4472C4');
        $headerStyle->setFontColor('FFFFFF');

        $titleStyle = new $styleClass();
        $titleStyle->setFontBold();
        $titleStyle->setFontSize(14);

        $positiveStyle = new $styleClass();
        $positiveStyle->setFontColor('006600');

        $negativeStyle = new $styleClass();
        $negativeStyle->setFontColor('CC0000');

        $totalStyle = new $styleClass();
        $totalStyle->setFontBold();
        $totalStyle->setBackgroundColor('E2EFDA');

        $isFirst = true;
        foreach ($this->sheets as $sheet) {
            if (!$isFirst) {
                $writer->addNewSheetAndMakeItCurrent();
            }
            $isFirst = false;

            $currentSheet = $writer->getCurrentSheet();
            $currentSheet->setName($sheet->getName());

            $sheetHeaders = $sheet->getHeaders();
            $sheetColumnConfig = $sheet->getColumnConfig();
            $sheetReportHeader = $sheet->getReportHeader();
            $sheetShowTotals = $sheet->hasTotals();
            $sheetTotalColumns = $sheet->getTotalColumns();
            $sheetTotalsLabel = $sheet->getTotalsLabel();

            // Write report header if present
            if ($sheetReportHeader) {
                foreach ($sheetReportHeader->getRows() as $row) {
                    $cells = [$cellClass::fromValue($row['text'], $titleStyle)];
                    $writer->addRow(new $rowClass($cells));
                }
                $writer->addRow(new $rowClass([]));
            }

            // Write column headers
            if ($this->includeHeaders && !empty($sheetHeaders)) {
                $headerCells = [];
                foreach ($sheetHeaders as $header) {
                    $headerCells[] = $cellClass::fromValue($header, $headerStyle);
                }
                $writer->addRow(new $rowClass($headerCells));
            }

            // Track totals
            $totals = [];
            if ($sheetShowTotals) {
                foreach ($sheetHeaders as $header) {
                    $totals[$header] = 0;
                }
            }

            $columnKeys = array_keys($sheetColumnConfig);

            // Write data rows with conditional coloring
            $sheetData = $this->getSheetDataAsGenerator($sheet);
            foreach ($sheetData as $row) {
                $cells = [];
                $values = array_values($row);
                $keys = array_keys($row);

                foreach ($values as $index => $value) {
                    $key = $keys[$index] ?? $index;
                    $style = null;

                    // Check for conditional coloring
                    if ($this->conditionalColoring && isset($sheetColumnConfig[$key])) {
                        $config = $sheetColumnConfig[$key];
                        $colorConditional = $config['color_conditional'] ?? false;

                        if ($colorConditional && is_numeric($value)) {
                            if ($value > 0) {
                                $style = $positiveStyle;
                            } elseif ($value < 0) {
                                $style = $negativeStyle;
                            }
                        }
                    }

                    // Accumulate totals
                    if ($sheetShowTotals && is_numeric($value)) {
                        $header = $sheetHeaders[$index] ?? $key;
                        if (isset($totals[$header])) {
                            $totals[$header] += $value;
                        }
                    }

                    $cells[] = $style ? $cellClass::fromValue($value, $style) : $cellClass::fromValue($value);
                }

                $writer->addRow(new $rowClass($cells));
            }

            // Write totals row if enabled
            if ($sheetShowTotals && !empty($totals)) {
                $totalCells = [];
                $isFirstCol = true;

                foreach ($sheetHeaders as $index => $header) {
                    $key = $columnKeys[$index] ?? $header;
                    $value = '';

                    if ($isFirstCol) {
                        $value = $sheetTotalsLabel;
                        $isFirstCol = false;
                    } elseif (in_array($key, $sheetTotalColumns) || in_array($header, $sheetTotalColumns)) {
                        $value = $totals[$header] ?? 0;
                    }

                    $totalCells[] = $cellClass::fromValue($value, $totalStyle);
                }

                $writer->addRow(new $rowClass($totalCells));
            }
        }

        $writer->close();
        return true;
    }

    /**
     * Export multiple sheets as XML
     */
    protected function exportMultipleSheetsAsXml(string $path): bool
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open file for writing: {$path}");
        }

        try {
            $this->writeXmlHeader($handle);
            $this->writeXmlStyles($handle);

            // Write each sheet
            foreach ($this->sheets as $sheet) {
                $this->writeSheetAsXml($handle, $sheet);
            }

            $this->writeXmlFooter($handle);
            return true;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Write a single sheet to XML
     */
    protected function writeSheetAsXml($handle, Sheet $sheet): void
    {
        $sheetName = $sheet->getName();
        $headers = $sheet->getHeaders();
        $columnConfig = $sheet->getColumnConfig();
        $reportHeader = $sheet->getReportHeader();
        $showTotals = $sheet->hasTotals();
        $totalColumns = $sheet->getTotalColumns();
        $totalsLabel = $sheet->getTotalsLabel();

        // Temporarily set sheet-specific config
        $originalColumnConfig = $this->columnConfig;
        $originalReportHeader = $this->reportHeader;
        $originalShowTotals = $this->showTotals;
        $originalTotalColumns = $this->totalColumns;
        $originalTotalsLabel = $this->totalsLabel;

        $this->columnConfig = $columnConfig;
        $this->reportHeader = $reportHeader;
        $this->showTotals = $showTotals;
        $this->totalColumns = $totalColumns;
        $this->totalsLabel = $totalsLabel;

        // Write worksheet
        $this->writeXmlWorksheetStartWithName($handle, $sheetName);

        $columnCount = count($headers);

        // Write report header if present
        if ($reportHeader) {
            $this->writeReportHeader($handle, $columnCount);
        }

        // Write column headers
        if ($this->includeHeaders && !empty($headers)) {
            $this->writeXmlRow($handle, $headers, true);
        }

        // Write data rows and calculate totals
        $totals = [];
        if ($showTotals) {
            $totals = $this->initializeTotals($headers);
        }

        $sheetData = $this->getSheetDataAsGenerator($sheet);
        foreach ($sheetData as $row) {
            $values = array_values($row);
            $this->writeXmlRow($handle, $values, false);

            if ($showTotals) {
                $this->accumulateTotals($totals, $row);
            }
        }

        // Write totals row
        if ($showTotals && !empty($totals)) {
            $this->writeTotalsRow($handle, $totals, $headers);
        }

        $this->writeXmlWorksheetEnd($handle);

        // Restore original config
        $this->columnConfig = $originalColumnConfig;
        $this->reportHeader = $originalReportHeader;
        $this->showTotals = $originalShowTotals;
        $this->totalColumns = $originalTotalColumns;
        $this->totalsLabel = $originalTotalsLabel;
    }

    /**
     * Get sheet data as generator
     */
    protected function getSheetDataAsGenerator(Sheet $sheet): Generator
    {
        $data = $sheet->getData();
        $columns = $sheet->getColumns();
        $transformer = $sheet->getRowTransformer();

        // Handle different data types
        if ($data instanceof Generator) {
            foreach ($data as $item) {
                yield $this->processSheetRow($item, $columns, $transformer);
            }
            return;
        }

        if (is_iterable($data)) {
            foreach ($data as $item) {
                yield $this->processSheetRow($item, $columns, $transformer);
            }
            return;
        }

        // Single item
        if ($data !== null) {
            yield $this->processSheetRow($data, $columns, $transformer);
        }
    }

    /**
     * Process a single row for a sheet
     */
    protected function processSheetRow(mixed $item, array $columns, ?\Closure $transformer): array
    {
        // Convert to array
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

        // Apply transformer
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
                if ($value === null) {
                    break;
                }
            }
            return $value;
        }

        return $data[$key] ?? null;
    }

    /**
     * Write worksheet start with custom name
     */
    protected function writeXmlWorksheetStartWithName($handle, string $name): void
    {
        fwrite($handle, '<Worksheet ss:Name="' . htmlspecialchars($name) . '">' . "\n");
        fwrite($handle, '<Table>' . "\n");
    }

    public function download(Generator $data, array $headers, string $filename): mixed
    {
        return $this->stream($data, $headers, $filename);
    }

    public function toString(Generator $data, array $headers): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_export_');
        $this->export($data, $headers, $tempFile);
        $content = file_get_contents($tempFile);
        unlink($tempFile);
        return $content;
    }

    public function stream(Generator $data, array $headers, string $filename): mixed
    {
        $filename = $this->ensureExtension($filename);

        // Handle multiple sheets
        if ($this->hasMultipleSheets()) {
            return $this->streamMultipleSheets($filename);
        }

        if ($this->useOpenSpout) {
            return $this->streamWithOpenSpout($data, $headers, $filename);
        }

        return $this->streamAsXml($data, $headers, $filename);
    }

    /**
     * Stream multiple sheets
     */
    protected function streamMultipleSheets(string $filename): StreamedResponse
    {
        if ($this->useOpenSpout) {
            return $this->streamMultipleSheetsWithOpenSpout($filename);
        }

        return $this->streamMultipleSheetsAsXml($filename);
    }

    /**
     * Stream multiple sheets with OpenSpout
     */
    protected function streamMultipleSheetsWithOpenSpout(string $filename): StreamedResponse
    {
        $sheets = $this->sheets;
        $includeHeaders = $this->includeHeaders;

        return new StreamedResponse(function () use ($sheets, $includeHeaders) {
            $writerClass = 'OpenSpout\Writer\XLSX\Writer';
            $rowClass = 'OpenSpout\Common\Entity\Row';

            $writer = new $writerClass();
            $writer->openToBrowser('export.xlsx');

            $isFirst = true;
            foreach ($sheets as $sheet) {
                if (!$isFirst) {
                    $writer->addNewSheetAndMakeItCurrent();
                }
                $isFirst = false;

                $currentSheet = $writer->getCurrentSheet();
                $currentSheet->setName($sheet->getName());

                $sheetData = $this->getSheetDataAsGenerator($sheet);
                $sheetHeaders = $sheet->getHeaders();

                if ($includeHeaders && !empty($sheetHeaders)) {
                    $headerRow = $rowClass::fromValues($sheetHeaders);
                    $writer->addRow($headerRow);
                }

                foreach ($sheetData as $row) {
                    $dataRow = $rowClass::fromValues(array_values($row));
                    $writer->addRow($dataRow);
                }
            }

            $writer->close();
        }, 200, [
            'Content-Type' => $this->getContentType(),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Stream multiple sheets as XML
     */
    protected function streamMultipleSheetsAsXml(string $filename): StreamedResponse
    {
        $filename = str_replace('.xlsx', '.xml', $filename);
        $sheets = $this->sheets;

        return new StreamedResponse(function () use ($sheets) {
            $handle = fopen('php://output', 'w');

            $this->writeXmlHeader($handle);
            $this->writeXmlStyles($handle);

            foreach ($sheets as $sheet) {
                $this->writeSheetAsXml($handle, $sheet);

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            $this->writeXmlFooter($handle);
            fclose($handle);
        }, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Export using OpenSpout library (if available)
     */
    protected function exportWithOpenSpout(Generator $data, array $headers, string $path): bool
    {
        $writerClass = 'OpenSpout\Writer\XLSX\Writer';
        $rowClass = 'OpenSpout\Common\Entity\Row';
        $cellClass = 'OpenSpout\Common\Entity\Cell';
        $styleClass = 'OpenSpout\Common\Entity\Style\Style';
        $colorClass = 'OpenSpout\Common\Entity\Style\Color';

        $writer = new $writerClass();
        $writer->openToFile($path);

        // Prepare header style
        $headerStyle = new $styleClass();
        $headerStyle->setFontBold();
        $headerStyle->setBackgroundColor('4472C4');
        $headerStyle->setFontColor('FFFFFF');

        // Prepare positive/negative styles for conditional coloring
        $positiveStyle = new $styleClass();
        $positiveStyle->setFontColor('006600'); // Dark green

        $negativeStyle = new $styleClass();
        $negativeStyle->setFontColor('CC0000'); // Dark red

        // Write report header if present
        if ($this->reportHeader) {
            $titleStyle = new $styleClass();
            $titleStyle->setFontBold();
            $titleStyle->setFontSize(14);

            foreach ($this->reportHeader->getRows() as $row) {
                $cells = [$cellClass::fromValue($row['text'], $titleStyle)];
                $writer->addRow(new $rowClass($cells));
            }
            // Empty row after header
            $writer->addRow(new $rowClass([]));
        }

        // Write column headers
        if ($this->includeHeaders && !empty($headers)) {
            $headerCells = [];
            foreach ($headers as $header) {
                $headerCells[] = $cellClass::fromValue($header, $headerStyle);
            }
            $writer->addRow(new $rowClass($headerCells));
        }

        // Get column keys for conditional coloring lookup
        $columnKeys = array_keys($this->columnConfig);

        // Track totals
        $totals = [];
        if ($this->showTotals) {
            foreach ($headers as $header) {
                $totals[$header] = 0;
            }
        }

        // Write data rows with conditional coloring
        foreach ($data as $row) {
            $cells = [];
            $values = array_values($row);
            $keys = array_keys($row);

            foreach ($values as $index => $value) {
                $key = $keys[$index] ?? $index;
                $style = null;

                // Check for conditional coloring
                if ($this->conditionalColoring && isset($this->columnConfig[$key])) {
                    $config = $this->columnConfig[$key];
                    $colorConditional = $config['color_conditional'] ?? false;

                    if ($colorConditional && is_numeric($value)) {
                        if ($value > 0) {
                            $style = $positiveStyle;
                        } elseif ($value < 0) {
                            $style = $negativeStyle;
                        }
                    }
                }

                // Accumulate totals
                if ($this->showTotals && is_numeric($value)) {
                    $header = $headers[$index] ?? $key;
                    if (isset($totals[$header])) {
                        $totals[$header] += $value;
                    }
                }

                $cells[] = $style ? $cellClass::fromValue($value, $style) : $cellClass::fromValue($value);
            }

            $writer->addRow(new $rowClass($cells));
        }

        // Write totals row if enabled
        if ($this->showTotals && !empty($totals)) {
            $totalStyle = new $styleClass();
            $totalStyle->setFontBold();
            $totalStyle->setBackgroundColor('E2EFDA'); // Light green background

            $totalCells = [];
            $isFirst = true;

            foreach ($headers as $index => $header) {
                $key = $columnKeys[$index] ?? $header;
                $value = '';

                if ($isFirst) {
                    $value = $this->totalsLabel;
                    $isFirst = false;
                } elseif (in_array($key, $this->totalColumns) || in_array($header, $this->totalColumns)) {
                    $value = $totals[$header] ?? 0;
                }

                $totalCells[] = $cellClass::fromValue($value, $totalStyle);
            }

            $writer->addRow(new $rowClass($totalCells));
        }

        $writer->close();
        return true;
    }

    /**
     * Stream using OpenSpout library
     */
    protected function streamWithOpenSpout(Generator $data, array $headers, string $filename): StreamedResponse
    {
        $writerClass = 'OpenSpout\Writer\XLSX\Writer';
        $rowClass = 'OpenSpout\Common\Entity\Row';
        $sheetName = $this->sheetName;
        $includeHeaders = $this->includeHeaders;

        return new StreamedResponse(function () use ($data, $headers, $writerClass, $rowClass, $sheetName, $includeHeaders) {
            $writer = new $writerClass();
            $writer->openToBrowser($sheetName . '.xlsx');

            // Write headers
            if ($includeHeaders && !empty($headers)) {
                $headerRow = $rowClass::fromValues($headers);
                $writer->addRow($headerRow);
            }

            // Write data rows
            foreach ($data as $row) {
                $dataRow = $rowClass::fromValues(array_values($row));
                $writer->addRow($dataRow);
            }

            $writer->close();
        }, 200, [
            'Content-Type' => $this->getContentType(),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Export as Excel XML (SpreadsheetML) - no dependencies required
     */
    protected function exportAsXml(Generator $data, array $headers, string $path): bool
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open file for writing: {$path}");
        }

        try {
            $this->writeXmlHeader($handle);
            $this->writeXmlStyles($handle);
            $this->writeXmlWorksheetStart($handle);

            $columnCount = count($headers);

            // Write report header
            $this->writeReportHeader($handle, $columnCount);

            // Write column headers
            if ($this->includeHeaders && !empty($headers)) {
                $this->writeXmlRow($handle, $headers, true);
            }

            // Write data rows and calculate totals
            $totals = [];
            if ($this->showTotals) {
                $totals = $this->initializeTotals($headers);
            }

            foreach ($data as $row) {
                $values = array_values($row);
                $this->writeXmlRow($handle, $values, false);

                if ($this->showTotals) {
                    $this->accumulateTotals($totals, $row);
                }
            }

            // Write totals row
            if ($this->showTotals) {
                $this->writeTotalsRow($handle, $totals, $headers);
            }

            $this->writeXmlWorksheetEnd($handle);
            $this->writeXmlFooter($handle);

            return true;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Initialize totals array
     */
    protected function initializeTotals(array $headers): array
    {
        $totals = [];
        $keys = array_keys($this->columnConfig);

        foreach ($headers as $index => $header) {
            $key = $keys[$index] ?? $header;
            $config = $this->columnConfig[$key] ?? null;

            if ($config) {
                $type = $config['type'] ?? ColumnTypes::STRING;
                if (ColumnTypes::isNumeric($type)) {
                    if (empty($this->totalColumns) || in_array($key, $this->totalColumns)) {
                        $totals[$key] = 0;
                    }
                }
            }
        }

        return $totals;
    }

    /**
     * Accumulate values into totals
     */
    protected function accumulateTotals(array &$totals, array $row): void
    {
        foreach ($totals as $key => $value) {
            $rowValue = $row[$key] ?? 0;
            $totals[$key] += is_numeric($rowValue) ? (float) $rowValue : 0;
        }
    }

    /**
     * Write totals row
     */
    protected function writeTotalsRow($handle, array $totals, array $headers): void
    {
        $keys = array_keys($this->columnConfig);
        $row = [];
        $first = true;

        foreach ($headers as $index => $header) {
            $key = $keys[$index] ?? $header;

            if ($first) {
                $row[$key] = $this->totalsLabel;
                $first = false;
            } else {
                $row[$key] = $totals[$key] ?? null;
            }
        }

        $this->writeXmlRow($handle, $row, false, true);
    }

    /**
     * Stream as Excel XML
     */
    protected function streamAsXml(Generator $data, array $headers, string $filename): StreamedResponse
    {
        // Change extension to .xml for SpreadsheetML format
        $filename = str_replace('.xlsx', '.xml', $filename);

        $columnConfig = $this->columnConfig;
        $reportHeader = $this->reportHeader;
        $showTotals = $this->showTotals;
        $totalColumns = $this->totalColumns;
        $totalsLabel = $this->totalsLabel;
        $includeHeaders = $this->includeHeaders;

        return new StreamedResponse(function () use ($data, $headers, $columnConfig, $reportHeader, $showTotals, $totalColumns, $totalsLabel, $includeHeaders) {
            $handle = fopen('php://output', 'w');

            $this->writeXmlHeader($handle);
            $this->writeXmlStyles($handle);
            $this->writeXmlWorksheetStart($handle);

            $columnCount = count($headers);

            // Write report header
            $this->writeReportHeader($handle, $columnCount);

            // Write column headers
            if ($includeHeaders && !empty($headers)) {
                $this->writeXmlRow($handle, $headers, true);
            }

            // Write data rows and calculate totals
            $totals = [];
            if ($showTotals) {
                $totals = $this->initializeTotals($headers);
            }

            foreach ($data as $row) {
                $values = array_values($row);
                $this->writeXmlRow($handle, $values, false);

                if ($showTotals) {
                    $this->accumulateTotals($totals, $row);
                }

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            // Write totals row
            if ($showTotals) {
                $this->writeTotalsRow($handle, $totals, $headers);
            }

            $this->writeXmlWorksheetEnd($handle);
            $this->writeXmlFooter($handle);

            fclose($handle);
        }, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    protected function writeXmlHeader($handle): void
    {
        fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
        fwrite($handle, '<?mso-application progid="Excel.Sheet"?>' . "\n");
        fwrite($handle, '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n");
        fwrite($handle, '    xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n");
        fwrite($handle, '    xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n");
        fwrite($handle, '    xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n");
    }

    protected function writeXmlStyles($handle): void
    {
        fwrite($handle, '<Styles>' . "\n");

        // Default style
        fwrite($handle, '    <Style ss:ID="Default" ss:Name="Normal">' . "\n");
        fwrite($handle, '        <Alignment ss:Vertical="Bottom"/>' . "\n");
        fwrite($handle, '    </Style>' . "\n");

        // Header style
        fwrite($handle, '    <Style ss:ID="Header">' . "\n");
        fwrite($handle, '        <Font ss:Bold="1" ss:Color="#FFFFFF"/>' . "\n");
        fwrite($handle, '        <Interior ss:Color="#2C3E50" ss:Pattern="Solid"/>' . "\n");
        fwrite($handle, '        <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . "\n");
        fwrite($handle, '    </Style>' . "\n");

        // Company/Title header styles
        fwrite($handle, '    <Style ss:ID="CompanyHeader">' . "\n");
        fwrite($handle, '        <Font ss:Bold="1" ss:Size="14"/>' . "\n");
        fwrite($handle, '        <Alignment ss:Horizontal="Center"/>' . "\n");
        fwrite($handle, '    </Style>' . "\n");

        fwrite($handle, '    <Style ss:ID="TitleHeader">' . "\n");
        fwrite($handle, '        <Font ss:Bold="1" ss:Size="12"/>' . "\n");
        fwrite($handle, '        <Alignment ss:Horizontal="Center"/>' . "\n");
        fwrite($handle, '    </Style>' . "\n");

        fwrite($handle, '    <Style ss:ID="SubtitleHeader">' . "\n");
        fwrite($handle, '        <Font ss:Size="11"/>' . "\n");
        fwrite($handle, '        <Alignment ss:Horizontal="Center"/>' . "\n");
        fwrite($handle, '    </Style>' . "\n");

        // Amount styles with conditional coloring
        fwrite($handle, '    <Style ss:ID="AmountPositive">' . "\n");
        fwrite($handle, '        <Font ss:Color="#006400"/>' . "\n");
        fwrite($handle, '        <NumberFormat ss:Format="' . $this->getNumberFormat(ColumnTypes::AMOUNT) . '"/>' . "\n");
        fwrite($handle, '        <Alignment ss:Horizontal="Right"/>' . "\n");
        fwrite($handle, '    </Style>' . "\n");

        fwrite($handle, '    <Style ss:ID="AmountNegative">' . "\n");
        fwrite($handle, '        <Font ss:Color="#8B0000"/>' . "\n");
        fwrite($handle, '        <NumberFormat ss:Format="' . $this->getNumberFormat(ColumnTypes::AMOUNT) . '"/>' . "\n");
        fwrite($handle, '        <Alignment ss:Horizontal="Right"/>' . "\n");
        fwrite($handle, '    </Style>' . "\n");

        fwrite($handle, '    <Style ss:ID="Amount">' . "\n");
        fwrite($handle, '        <NumberFormat ss:Format="' . $this->getNumberFormat(ColumnTypes::AMOUNT) . '"/>' . "\n");
        fwrite($handle, '        <Alignment ss:Horizontal="Right"/>' . "\n");
        fwrite($handle, '    </Style>' . "\n");

        // Integer style
        fwrite($handle, '    <Style ss:ID="Integer">' . "\n");
        fwrite($handle, '        <NumberFormat ss:Format="' . $this->getNumberFormat(ColumnTypes::INTEGER) . '"/>' . "\n");
        fwrite($handle, '        <Alignment ss:Horizontal="Right"/>' . "\n");
        fwrite($handle, '    </Style>' . "\n");

        // Percentage style
        fwrite($handle, '    <Style ss:ID="Percentage">' . "\n");
        fwrite($handle, '        <NumberFormat ss:Format="' . $this->getNumberFormat(ColumnTypes::PERCENTAGE) . '"/>' . "\n");
        fwrite($handle, '        <Alignment ss:Horizontal="Right"/>' . "\n");
        fwrite($handle, '    </Style>' . "\n");

        // Date style
        fwrite($handle, '    <Style ss:ID="Date">' . "\n");
        fwrite($handle, '        <NumberFormat ss:Format="DD-MMM-YYYY"/>' . "\n");
        fwrite($handle, '        <Alignment ss:Horizontal="Center"/>' . "\n");
        fwrite($handle, '    </Style>' . "\n");

        // DateTime style
        fwrite($handle, '    <Style ss:ID="DateTime">' . "\n");
        fwrite($handle, '        <NumberFormat ss:Format="DD-MMM-YYYY HH:MM:SS"/>' . "\n");
        fwrite($handle, '        <Alignment ss:Horizontal="Center"/>' . "\n");
        fwrite($handle, '    </Style>' . "\n");

        // Totals row style
        fwrite($handle, '    <Style ss:ID="TotalRow">' . "\n");
        fwrite($handle, '        <Font ss:Bold="1"/>' . "\n");
        fwrite($handle, '        <Interior ss:Color="#E8E8E8" ss:Pattern="Solid"/>' . "\n");
        fwrite($handle, '        <Borders>' . "\n");
        fwrite($handle, '            <Border ss:Position="Top" ss:LineStyle="Double" ss:Weight="3"/>' . "\n");
        fwrite($handle, '            <Border ss:Position="Bottom" ss:LineStyle="Double" ss:Weight="3"/>' . "\n");
        fwrite($handle, '        </Borders>' . "\n");
        fwrite($handle, '    </Style>' . "\n");

        fwrite($handle, '</Styles>' . "\n");
    }

    /**
     * Get number format based on column type and locale
     */
    protected function getNumberFormat(string $type): string
    {
        return match ($type) {
            ColumnTypes::AMOUNT, ColumnTypes::AMOUNT_PLAIN, ColumnTypes::QUANTITY =>
                $this->locale === 'en_IN' ? '#,##,##0.00' : '#,##0.00',
            ColumnTypes::INTEGER => '#,##0',
            ColumnTypes::PERCENTAGE => '0.00%',
            ColumnTypes::DATE => 'DD-MMM-YYYY',
            ColumnTypes::DATETIME => 'DD-MMM-YYYY HH:MM:SS',
            default => 'General',
        };
    }

    protected function writeXmlWorksheetStart($handle): void
    {
        fwrite($handle, '<Worksheet ss:Name="' . htmlspecialchars($this->sheetName) . '">' . "\n");
        fwrite($handle, '<Table>' . "\n");
    }

    protected function writeXmlRow($handle, array $values, bool $isHeader, bool $isTotalRow = false): void
    {
        $style = '';
        if ($isHeader) {
            $style = ' ss:StyleID="Header"';
        } elseif ($isTotalRow) {
            $style = ' ss:StyleID="TotalRow"';
        }

        fwrite($handle, '<Row' . $style . '>' . "\n");

        $colIndex = 0;
        foreach ($values as $key => $value) {
            $cellStyle = $this->getCellStyle($key, $value, $isHeader, $isTotalRow, $colIndex);
            $type = $this->getXmlCellType($value);
            $escapedValue = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

            $styleAttr = $cellStyle ? ' ss:StyleID="' . $cellStyle . '"' : '';
            fwrite($handle, '    <Cell' . $styleAttr . '><Data ss:Type="' . $type . '">' . $escapedValue . '</Data></Cell>' . "\n");
            $colIndex++;
        }

        fwrite($handle, '</Row>' . "\n");
    }

    /**
     * Get cell style based on column type and value
     */
    protected function getCellStyle(string|int $key, mixed $value, bool $isHeader, bool $isTotalRow, int $colIndex): ?string
    {
        if ($isHeader) {
            return 'Header';
        }

        if ($isTotalRow) {
            return 'TotalRow';
        }

        // Check column configuration
        $columnKey = is_int($key) ? array_keys($this->columnConfig)[$key] ?? null : $key;

        if ($columnKey && isset($this->columnConfig[$columnKey])) {
            $config = $this->columnConfig[$columnKey];
            $type = $config['type'] ?? ColumnTypes::STRING;
            $colorConditional = $config['color_conditional'] ?? false;

            // Handle conditional coloring for amounts
            if ($this->conditionalColoring && $colorConditional && is_numeric($value)) {
                if ($value > 0) {
                    return 'AmountPositive';
                } elseif ($value < 0) {
                    return 'AmountNegative';
                }
                return 'Amount';
            }

            return match ($type) {
                ColumnTypes::AMOUNT, ColumnTypes::AMOUNT_PLAIN, ColumnTypes::QUANTITY => 'Amount',
                ColumnTypes::INTEGER => 'Integer',
                ColumnTypes::PERCENTAGE => 'Percentage',
                ColumnTypes::DATE => 'Date',
                ColumnTypes::DATETIME => 'DateTime',
                default => null,
            };
        }

        return null;
    }

    /**
     * Write report header rows
     */
    protected function writeReportHeader($handle, int $columnCount): int
    {
        if (!$this->reportHeader) {
            return 0;
        }

        $rows = $this->reportHeader->getRows();
        $rowsWritten = 0;

        foreach ($rows as $row) {
            $style = match ($row['style']) {
                'company' => 'CompanyHeader',
                'title' => 'TitleHeader',
                'subtitle', 'info', 'custom', 'meta' => 'SubtitleHeader',
                default => null,
            };

            $styleAttr = $style ? ' ss:StyleID="' . $style . '"' : '';

            // Write merged cell spanning all columns
            fwrite($handle, '<Row' . $styleAttr . '>' . "\n");
            fwrite($handle, '    <Cell ss:MergeAcross="' . ($columnCount - 1) . '"' . $styleAttr . '>' . "\n");
            fwrite($handle, '        <Data ss:Type="String">' . htmlspecialchars($row['text']) . '</Data>' . "\n");
            fwrite($handle, '    </Cell>' . "\n");
            fwrite($handle, '</Row>' . "\n");

            $rowsWritten++;
        }

        // Add empty row after header
        if ($rowsWritten > 0) {
            fwrite($handle, '<Row></Row>' . "\n");
            $rowsWritten++;
        }

        return $rowsWritten;
    }

    protected function writeXmlWorksheetEnd($handle): void
    {
        fwrite($handle, '</Table>' . "\n");
        fwrite($handle, '</Worksheet>' . "\n");
    }

    protected function writeXmlFooter($handle): void
    {
        fwrite($handle, '</Workbook>' . "\n");
    }

    protected function getXmlCellType(mixed $value): string
    {
        if (is_numeric($value)) {
            return 'Number';
        }
        if ($value instanceof \DateTimeInterface) {
            return 'DateTime';
        }
        return 'String';
    }

    public function getExtension(): string
    {
        return $this->useOpenSpout ? 'xlsx' : 'xml';
    }

    public function getContentType(): string
    {
        return $this->useOpenSpout
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'application/vnd.ms-excel';
    }

    protected function ensureExtension(string $filename): string
    {
        $ext = $this->useOpenSpout ? '.xlsx' : '.xml';
        if (!str_ends_with(strtolower($filename), $ext)) {
            // Remove any existing excel extension first
            $filename = preg_replace('/\.(xlsx?|xml)$/i', '', $filename);
            return $filename . $ext;
        }
        return $filename;
    }
}
