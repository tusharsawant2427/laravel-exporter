<?php

namespace LaravelExporter\Traits;

use LaravelExporter\ColumnTypes;
use LaravelExporter\Support\ColumnCollection;

/**
 * Has Totals Trait
 *
 * Provides functionality for calculating and adding
 * totals/subtotals to exports.
 */
trait HasTotals
{
    /**
     * Columns that should have totals calculated
     */
    protected array $totalColumns = [];

    /**
     * Whether to show totals row
     */
    protected bool $showTotals = false;

    /**
     * Totals label
     */
    protected string $totalsLabel = 'TOTAL';

    /**
     * Calculated totals
     */
    protected array $calculatedTotals = [];

    /**
     * Enable totals for specified columns
     */
    public function withTotals(array $columns = []): static
    {
        $this->showTotals = true;
        $this->totalColumns = $columns;
        return $this;
    }

    /**
     * Set the totals label
     */
    public function totalsLabel(string $label): static
    {
        $this->totalsLabel = $label;
        return $this;
    }

    /**
     * Disable totals
     */
    public function withoutTotals(): static
    {
        $this->showTotals = false;
        return $this;
    }

    /**
     * Check if totals are enabled
     */
    public function hasTotals(): bool
    {
        return $this->showTotals;
    }

    /**
     * Get columns to total
     */
    public function getTotalColumns(): array
    {
        return $this->totalColumns;
    }

    /**
     * Get totals label
     */
    public function getTotalsLabel(): string
    {
        return $this->totalsLabel;
    }

    /**
     * Calculate totals from data
     */
    public function calculateTotals(iterable $data, array $columnConfig): array
    {
        $totals = [];

        // Initialize totals for numeric columns
        foreach ($columnConfig as $key => $config) {
            $type = $config['type'] ?? ColumnTypes::STRING;

            // If specific columns are set, only total those
            if (!empty($this->totalColumns) && !in_array($key, $this->totalColumns)) {
                continue;
            }

            if (ColumnTypes::isNumeric($type)) {
                $totals[$key] = 0;
            }
        }

        // Sum values
        foreach ($data as $row) {
            foreach ($totals as $key => $value) {
                $rowValue = is_array($row) ? ($row[$key] ?? 0) : (data_get($row, $key) ?? 0);
                $totals[$key] += (float) $rowValue;
            }
        }

        $this->calculatedTotals = $totals;
        return $totals;
    }

    /**
     * Get calculated totals
     */
    public function getCalculatedTotals(): array
    {
        return $this->calculatedTotals;
    }

    /**
     * Build totals row array
     */
    public function buildTotalsRow(array $columnConfig): array
    {
        $row = [];
        $first = true;

        foreach ($columnConfig as $key => $config) {
            if ($first) {
                $row[$key] = $this->totalsLabel;
                $first = false;
                continue;
            }

            $row[$key] = $this->calculatedTotals[$key] ?? null;
        }

        return $row;
    }
}
