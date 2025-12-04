<?php

namespace LaravelExporter\Concerns;

use LaravelExporter\Imports\Failure;

/**
 * Skip rows that fail validation
 *
 * Similar to Maatwebsite\Excel\Concerns\SkipsOnFailure
 */
interface SkipsOnFailure
{
    /**
     * Handle validation failures
     *
     * @param Failure[] $failures Array of validation failures
     * @return void
     */
    public function onFailure(Failure ...$failures): void;
}
