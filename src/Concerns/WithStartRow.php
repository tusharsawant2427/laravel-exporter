<?php

namespace LaravelExporter\Concerns;

/**
 * Start reading from a specific row
 *
 * Similar to Maatwebsite\Excel\Concerns\WithStartRow
 */
interface WithStartRow
{
    /**
     * Get the row number to start reading from
     *
     * @return int Row number (1-indexed)
     */
    public function startRow(): int;
}
