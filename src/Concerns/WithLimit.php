<?php

namespace LaravelExporter\Concerns;

/**
 * Limit the number of rows to import
 *
 * Similar to Maatwebsite\Excel\Concerns\WithLimit
 */
interface WithLimit
{
    /**
     * Get the maximum number of rows to import
     *
     * @return int Maximum rows (0 for unlimited)
     */
    public function limit(): int;
}
