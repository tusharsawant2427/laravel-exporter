<?php

namespace LaravelExporter\Traits;

/**
 * Has Advanced Excel Features Trait
 *
 * Provides advanced Excel features using PhpSpreadsheet:
 * - Excel Formulas
 * - Dynamic Conditional Formatting
 * - Cell Merging
 *
 * Note: freezeHeader, autoFilter, autoSize are in HasStyling trait
 */
trait HasAdvancedExcel
{
    /**
     * Use PhpSpreadsheet for advanced features
     */
    protected bool $usePhpSpreadsheet = false;

    /**
     * Use Excel formulas for totals (SUM, etc.)
     */
    protected bool $useFormulas = true;

    /**
     * Enable dynamic conditional formatting (Excel-native)
     */
    protected bool $dynamicConditionalFormatting = true;

    /**
     * Cells to merge
     * @var array<string>
     */
    protected array $mergedCells = [];

    /**
     * Custom formulas to add
     * @var array<array{cell: string, formula: string}>
     */
    protected array $customFormulas = [];

    /**
     * Enable advanced Excel features using PhpSpreadsheet
     */
    public function withAdvancedExcel(bool $enabled = true): static
    {
        $this->usePhpSpreadsheet = $enabled;
        return $this;
    }

    /**
     * Use Excel formulas for calculations
     */
    public function withFormulas(bool $enabled = true): static
    {
        $this->useFormulas = $enabled;
        return $this;
    }

    /**
     * Enable dynamic conditional formatting
     * This creates Excel-native conditional formatting rules
     * that update when data changes
     */
    public function withDynamicConditionalFormatting(bool $enabled = true): static
    {
        $this->dynamicConditionalFormatting = $enabled;
        return $this;
    }

    /**
     * Merge cells
     *
     * @param string $range Cell range (e.g., 'A1:D1')
     */
    public function mergeCells(string $range): static
    {
        $this->mergedCells[] = $range;
        return $this;
    }

    /**
     * Add multiple merged cell ranges
     *
     * @param array<string> $ranges
     */
    public function mergeMultipleCells(array $ranges): static
    {
        $this->mergedCells = array_merge($this->mergedCells, $ranges);
        return $this;
    }

    /**
     * Add a custom Excel formula
     *
     * @param string $cell Cell reference (e.g., 'E10')
     * @param string $formula Excel formula (e.g., '=SUM(E2:E9)')
     */
    public function addFormula(string $cell, string $formula): static
    {
        $this->customFormulas[] = [
            'cell' => $cell,
            'formula' => $formula,
        ];
        return $this;
    }

    /**
     * Add multiple formulas
     *
     * @param array<string, string> $formulas ['cell' => 'formula']
     */
    public function addFormulas(array $formulas): static
    {
        foreach ($formulas as $cell => $formula) {
            $this->addFormula($cell, $formula);
        }
        return $this;
    }

    /**
     * Check if advanced Excel features are enabled
     */
    public function hasAdvancedExcel(): bool
    {
        return $this->usePhpSpreadsheet;
    }

    /**
     * Check if formulas are enabled
     */
    public function hasFormulas(): bool
    {
        return $this->useFormulas;
    }

    /**
     * Check if dynamic conditional formatting is enabled
     */
    public function hasDynamicConditionalFormatting(): bool
    {
        return $this->dynamicConditionalFormatting;
    }

    /**
     * Get merged cells
     */
    public function getMergedCells(): array
    {
        return $this->mergedCells;
    }

    /**
     * Get custom formulas
     */
    public function getCustomFormulas(): array
    {
        return $this->customFormulas;
    }

    /**
     * Get advanced Excel options for format exporter
     */
    protected function getAdvancedExcelOptions(): array
    {
        return [
            'use_phpspreadsheet' => $this->usePhpSpreadsheet,
            'use_formulas' => $this->useFormulas,
            'dynamic_conditional_formatting' => $this->dynamicConditionalFormatting,
            'merged_cells' => $this->mergedCells,
            'custom_formulas' => $this->customFormulas,
            'freeze_header' => true,   // Use default, controlled via HasStyling
            'auto_filter' => true,     // Use default, controlled via HasStyling
            'auto_size' => true,       // Use default
        ];
    }
}
