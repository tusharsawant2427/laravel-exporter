<?php

namespace LaravelExporter\Concerns;

/**
 * WithChunkReading Concern
 *
 * Implement this interface to enable chunked reading for large datasets.
 * This is more memory-efficient than cursor() for very large datasets
 * as it processes data in batches.
 *
 * Usage:
 *   class LargeExport implements FromQuery, WithChunkReading
 *   {
 *       public function chunkSize(): int
 *       {
 *           return 1000; // Process 1000 rows at a time
 *       }
 *   }
 */
interface WithChunkReading
{
    /**
     * Define the chunk size for reading data.
     *
     * @return int
     */
    public function chunkSize(): int;
}
