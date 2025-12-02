<?php

namespace LaravelExporter\Support;

use Closure;
use LaravelExporter\ColumnTypes;

/**
 * Sheet Definition
 *
 * Represents a single worksheet in a multi-sheet Excel export.
 * Each sheet can have its own data source, columns, headers, and styling.
 */
class Sheet
{
    protected string $name;
    protected mixed $data = null;
    protected array $columns = [];
    protected array $headers = [];
    protected ?ColumnCollection $columnCollection = null;
    protected ?Closure $rowTransformer = null;
    protected ?ReportHeader $reportHeader = null;
    protected bool $showTotals = false;
    protected array $totalColumns = [];
    protected string $totalsLabel = 'TOTAL';
    protected array $columnConfig = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Create a new sheet
     */
    public static function make(string $name): static
    {
        return new static($name);
    }

    /**
     * Set the sheet name
     */
    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set the data source
     */
    public function data(mixed $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Set columns (simple array or fluent callback)
     */
    public function columns(array|callable $columns): static
    {
        if (is_callable($columns)) {
            $this->columnCollection = ColumnCollection::make();
            $columns($this->columnCollection);
            $this->columns = $this->columnCollection->getKeys();
            $this->headers = $this->columnCollection->getHeaders();
            $this->columnConfig = $this->columnCollection->toConfig();
        } else {
            $this->columns = $columns;
        }
        return $this;
    }

    /**
     * Set custom headers
     */
    public function headers(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Set row transformer
     */
    public function transformRow(Closure $callback): static
    {
        $this->rowTransformer = $callback;
        return $this;
    }

    /**
     * Set report header
     */
    public function header(ReportHeader|callable $header): static
    {
        if (is_callable($header)) {
            $this->reportHeader = ReportHeader::make();
            $header($this->reportHeader);
        } else {
            $this->reportHeader = $header;
        }
        return $this;
    }

    /**
     * Enable totals row
     */
    public function withTotals(array $columns = []): static
    {
        $this->showTotals = true;
        $this->totalColumns = $columns;
        return $this;
    }

    /**
     * Set totals label
     */
    public function totalsLabel(string $label): static
    {
        $this->totalsLabel = $label;
        return $this;
    }

    // Getters

    public function getName(): string
    {
        return $this->name;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getColumnCollection(): ?ColumnCollection
    {
        return $this->columnCollection;
    }

    public function getColumnConfig(): array
    {
        return $this->columnConfig;
    }

    public function getRowTransformer(): ?Closure
    {
        return $this->rowTransformer;
    }

    public function getReportHeader(): ?ReportHeader
    {
        return $this->reportHeader;
    }

    public function hasReportHeader(): bool
    {
        return $this->reportHeader !== null;
    }

    public function hasTotals(): bool
    {
        return $this->showTotals;
    }

    public function getTotalColumns(): array
    {
        return $this->totalColumns;
    }

    public function getTotalsLabel(): string
    {
        return $this->totalsLabel;
    }

    /**
     * Convert to array for exporter options
     */
    public function toOptions(): array
    {
        return [
            'sheet_name' => $this->name,
            'column_config' => $this->columnConfig,
            'column_collection' => $this->columnCollection,
            'report_header' => $this->reportHeader,
            'show_totals' => $this->showTotals,
            'total_columns' => $this->totalColumns,
            'totals_label' => $this->totalsLabel,
        ];
    }
}
