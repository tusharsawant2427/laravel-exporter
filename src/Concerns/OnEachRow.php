<?php

namespace LaravelExporter\Concerns;

use LaravelExporter\Imports\Row;

/**
 * Process each row individually during import
 *
 * Similar to Maatwebsite\Excel\Concerns\OnEachRow
 */
interface OnEachRow
{
    /**
     * Handle each row during import
     *
     * @param Row $row The row being imported
     * @return void
     */
    public function onRow(Row $row): void;
}
