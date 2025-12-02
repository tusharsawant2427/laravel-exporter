<?php

namespace LaravelExporter\Traits;

use LaravelExporter\Support\Sheet;
use Illuminate\Support\Collection;

/**
 * Has Multiple Sheets Trait
 *
 * Provides functionality for exports with multiple worksheets.
 * Useful for:
 * - Monthly breakdowns
 * - Department-wise reports
 * - Summary + Details sheets
 * - Grouped data exports
 */
trait HasMultipleSheets
{
    /**
     * Sheets to be included in export
     *
     * @var array<string, Sheet>
     */
    protected array $sheets = [];

    /**
     * Add a sheet to the export
     */
    public function addSheet(Sheet $sheet): static
    {
        $this->sheets[$sheet->getName()] = $sheet;
        return $this;
    }

    /**
     * Add a sheet with fluent configuration
     */
    public function sheet(string $name, callable $callback): static
    {
        $sheet = Sheet::make($name);
        $callback($sheet);
        $this->sheets[$name] = $sheet;
        return $this;
    }

    /**
     * Set all sheets at once
     *
     * @param array<Sheet> $sheets
     */
    public function sheets(array $sheets): static
    {
        $this->sheets = [];
        foreach ($sheets as $sheet) {
            if ($sheet instanceof Sheet) {
                $this->sheets[$sheet->getName()] = $sheet;
            }
        }
        return $this;
    }

    /**
     * Get all sheets
     *
     * @return array<string, Sheet>
     */
    public function getSheets(): array
    {
        return $this->sheets;
    }

    /**
     * Check if multiple sheets are configured
     */
    public function hasMultipleSheets(): bool
    {
        return count($this->sheets) > 0;
    }

    /**
     * Get sheet count
     */
    public function getSheetCount(): int
    {
        return count($this->sheets);
    }

    /**
     * Get a specific sheet by name
     */
    public function getSheet(string $name): ?Sheet
    {
        return $this->sheets[$name] ?? null;
    }

    /**
     * Remove a sheet by name
     */
    public function removeSheet(string $name): static
    {
        unset($this->sheets[$name]);
        return $this;
    }

    /**
     * Create sheets from grouped data
     *
     * @param iterable $data The data to group
     * @param string $groupBy The key to group by
     * @param callable $sheetFactory Factory function: fn(Collection $groupData, string $groupKey): Sheet
     */
    public function sheetsFromGroupedData(iterable $data, string $groupBy, callable $sheetFactory): static
    {
        $collection = $data instanceof Collection ? $data : collect($data);
        $grouped = $collection->groupBy($groupBy);

        foreach ($grouped as $groupKey => $groupData) {
            $sheet = $sheetFactory($groupData, (string) $groupKey);
            if ($sheet instanceof Sheet) {
                $this->sheets[$sheet->getName()] = $sheet;
            }
        }

        return $this;
    }

    /**
     * Create monthly sheets from data
     *
     * @param iterable $data The data
     * @param string $dateColumn The date column to group by
     * @param callable $sheetFactory Factory function: fn(Collection $monthData, string $monthYear): Sheet
     */
    public function sheetsFromMonthlyData(iterable $data, string $dateColumn, callable $sheetFactory): static
    {
        $collection = $data instanceof Collection ? $data : collect($data);

        $grouped = $collection->groupBy(function ($item) use ($dateColumn) {
            $date = data_get($item, $dateColumn);
            if ($date instanceof \DateTimeInterface) {
                return $date->format('Y-m');
            }
            return date('Y-m', strtotime($date));
        });

        // Sort by month
        $grouped = $grouped->sortKeys();

        foreach ($grouped as $monthYear => $monthData) {
            $sheet = $sheetFactory($monthData, $monthYear);
            if ($sheet instanceof Sheet) {
                $this->sheets[$sheet->getName()] = $sheet;
            }
        }

        return $this;
    }

    /**
     * Create a summary sheet + detail sheets pattern
     *
     * @param callable $summaryFactory Factory for summary sheet: fn(): Sheet
     * @param iterable $data Data for detail sheets
     * @param string $groupBy Group key
     * @param callable $detailFactory Factory for detail sheets: fn(Collection $data, string $key): Sheet
     */
    public function withSummaryAndDetails(
        callable $summaryFactory,
        iterable $data,
        string $groupBy,
        callable $detailFactory
    ): static {
        // Add summary sheet first
        $summarySheet = $summaryFactory();
        if ($summarySheet instanceof Sheet) {
            $this->sheets['Summary'] = $summarySheet;
        }

        // Add detail sheets
        $this->sheetsFromGroupedData($data, $groupBy, $detailFactory);

        return $this;
    }
}
