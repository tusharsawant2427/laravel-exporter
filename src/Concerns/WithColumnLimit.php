<?php

namespace LaravelExporter\Concerns;

/**
 * Limit the columns to read
 *
 * Similar to Maatwebsite\Excel\Concerns\WithColumnLimit
 */
interface WithColumnLimit
{
    /**
     * Get the last column to read
     *
     * @return string Column letter (e.g., 'J' for columns A-J)
     */
    public function endColumn(): string;
}
