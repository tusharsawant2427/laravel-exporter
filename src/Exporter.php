<?php

namespace LaravelExporter;

use Closure;
use LaravelExporter\Support\ColumnCollection;
use LaravelExporter\Traits\HasTotals;
use LaravelExporter\Traits\HasReportHeader;
use LaravelExporter\Traits\HasStyling;
use LaravelExporter\Traits\HasMultipleSheets;
use LaravelExporter\Traits\HasAdvancedExcel;

class Exporter
{
    use HasTotals, HasReportHeader, HasStyling, HasMultipleSheets, HasAdvancedExcel;

    protected array $headers = [];
    protected array $columns = [];
    protected ?ColumnCollection $columnCollection = null;
    protected ?Closure $rowTransformer = null;
    protected int $chunkSize = 1000;
    protected string $format = 'csv';
    protected string $filename = 'export';
    protected array $formatOptions = [];
    protected string $locale = 'en_IN';
    protected bool $conditionalColoring = true;

    /**
     * Create a new export instance
     */
    public static function make(): static
    {
        return new static();
    }

    /**
     * Set the data source (Eloquent Builder, Collection, Array, or Object)
     */
    public function from(object|array $source): DataExporter
    {
        return new DataExporter($source, $this);
    }

    /**
     * Set custom headers for the export
     */
    public function headers(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Set which columns to export (simple array)
     */
    public function columns(array|callable $columns): static
    {
        if (is_callable($columns)) {
            $this->columnCollection = ColumnCollection::make();
            $columns($this->columnCollection);
            $this->columns = $this->columnCollection->getKeys();
            $this->headers = $this->columnCollection->getHeaders();
        } else {
            $this->columns = $columns;
        }
        return $this;
    }

    /**
     * Define columns using fluent API
     */
    public function defineColumns(callable $callback): static
    {
        $this->columnCollection = ColumnCollection::make();
        $callback($this->columnCollection);
        $this->columns = $this->columnCollection->getKeys();
        $this->headers = $this->columnCollection->getHeaders();
        return $this;
    }

    /**
     * Set a custom row transformer
     */
    public function transformRow(Closure $callback): static
    {
        $this->rowTransformer = $callback;
        return $this;
    }

    /**
     * Set the chunk size for processing large datasets
     */
    public function chunkSize(int $size): static
    {
        $this->chunkSize = $size;
        return $this;
    }

    /**
     * Set the export format (csv, xlsx, json)
     */
    public function format(string $format): static
    {
        $this->format = strtolower($format);
        return $this;
    }

    /**
     * Set the filename for the export
     */
    public function filename(string $filename): static
    {
        $this->filename = $filename;
        return $this;
    }

    /**
     * Set format-specific options
     */
    public function options(array $options): static
    {
        $this->formatOptions = $options;
        return $this;
    }

    /**
     * Set locale for number formatting
     */
    public function locale(string $locale): static
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * Enable/disable conditional coloring for amounts
     */
    public function conditionalColoring(bool $enabled = true): static
    {
        $this->conditionalColoring = $enabled;
        return $this;
    }

    /**
     * Shortcut to export as CSV
     */
    public function asCsv(): static
    {
        return $this->format('csv');
    }

    /**
     * Shortcut to export as Excel
     */
    public function asExcel(): static
    {
        return $this->format('xlsx');
    }

    /**
     * Shortcut to export as JSON
     */
    public function asJson(): static
    {
        return $this->format('json');
    }

    // Getters
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getColumnCollection(): ?ColumnCollection
    {
        return $this->columnCollection;
    }

    public function getRowTransformer(): ?Closure
    {
        return $this->rowTransformer;
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getFormatOptions(): array
    {
        // Merge enhanced options for Excel format
        $options = $this->formatOptions;

        if (in_array($this->format, ['xlsx', 'excel'])) {
            $options['locale'] = $this->locale;
            $options['conditional_coloring'] = $this->conditionalColoring;

            if ($this->columnCollection) {
                $options['column_config'] = $this->columnCollection->toConfig();
                $options['column_collection'] = $this->columnCollection;
            }

            if ($this->hasHeader()) {
                $options['report_header'] = $this->getHeader();
            }

            if ($this->hasStyling()) {
                $options['style_builder'] = $this->getStyleBuilder();
            }

            if ($this->hasTotals()) {
                $options['show_totals'] = true;
                $options['total_columns'] = $this->getTotalColumns();
                $options['totals_label'] = $this->getTotalsLabel();
            }

            // Multiple sheets support
            if ($this->hasMultipleSheets()) {
                $options['sheets'] = $this->getSheets();
            }

            // Advanced Excel features (PhpSpreadsheet)
            if ($this->hasAdvancedExcel()) {
                $options = array_merge($options, $this->getAdvancedExcelOptions());
            }
        }

        return $options;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function hasConditionalColoring(): bool
    {
        return $this->conditionalColoring;
    }
}
