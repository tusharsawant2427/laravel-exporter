<?php

namespace LaravelExporter\Concerns;

/**
 * Map specific cells to named values
 *
 * Similar to Maatwebsite\Excel\Concerns\WithMappedCells
 *
 * Useful for reading specific cells from a template
 */
interface WithMappedCells
{
    /**
     * Get the cell mapping
     *
     * @return array<string, string> ['name' => 'A1', 'date' => 'B2', ...]
     */
    public function mapping(): array;
}
