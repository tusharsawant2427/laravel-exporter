<?php

namespace LaravelExporter\Concerns;

/**
 * Upsert models instead of just inserting
 *
 * Similar to Maatwebsite\Excel\Concerns\WithUpserts
 */
interface WithUpserts
{
    /**
     * Get the unique key(s) for upsert
     *
     * @return string|array Column(s) that identify unique records
     */
    public function uniqueBy(): string|array;
}
