<?php

namespace LaravelExporter\Concerns;

/**
 * Indicate that the first row contains headings
 *
 * Similar to Maatwebsite\Excel\Concerns\WithHeadingRow
 *
 * When implemented, the first row will be used as array keys
 * instead of numeric indices.
 */
interface WithHeadingRow
{
    /**
     * Get the row number that contains the headings
     *
     * @return int Row number (1-indexed, default is 1)
     */
    public function headingRow(): int;
}
