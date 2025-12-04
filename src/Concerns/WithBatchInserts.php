<?php

namespace LaravelExporter\Concerns;

/**
 * Insert models in batches for better performance
 *
 * Similar to Maatwebsite\Excel\Concerns\WithBatchInserts
 */
interface WithBatchInserts
{
    /**
     * Get the batch size for inserts
     *
     * @return int Number of models to insert per batch
     */
    public function batchSize(): int;
}
