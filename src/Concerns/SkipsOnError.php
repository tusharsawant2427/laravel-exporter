<?php

namespace LaravelExporter\Concerns;

use Throwable;

/**
 * Skip rows that cause errors during import
 *
 * Similar to Maatwebsite\Excel\Concerns\SkipsOnError
 */
interface SkipsOnError
{
    /**
     * Handle a row that caused an error
     *
     * @param Throwable $e The error that occurred
     * @return void
     */
    public function onError(Throwable $e): void;
}
