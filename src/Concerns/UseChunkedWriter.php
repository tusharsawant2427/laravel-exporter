<?php

namespace LaravelExporter\Concerns;

/**
 * UseChunkedWriter Concern
 *
 * Implement this interface to use the ChunkedPhpSpreadsheetExporter
 * which is optimized for large datasets with styles.
 *
 * The chunked writer:
 * - Writes data in batches with memory cleanup
 * - Disables formula pre-calculation
 * - Uses fixed column widths instead of auto-size
 * - Still supports basic styling and formatting
 *
 * Usage:
 *   class LargeStyledExport implements FromQuery, UseChunkedWriter
 *   {
 *       public function useChunkedWriter(): bool
 *       {
 *           return true;
 *       }
 *   }
 */
interface UseChunkedWriter
{
    /**
     * Whether to use the chunked PhpSpreadsheet writer.
     *
     * @return bool
     */
    public function useChunkedWriter(): bool;
}
