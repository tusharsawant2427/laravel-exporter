<?php

namespace LaravelExporter\Concerns;

/**
 * UseHybridExporter Concern
 *
 * Uses both OpenSpout (for streaming data) and PhpSpreadsheet (for styling).
 *
 * Phase 1: OpenSpout writes raw data to disk (streaming, ~30MB)
 * Phase 2: PhpSpreadsheet applies styles to the file (~50MB extra)
 *
 * Total: ~80MB for 100K rows WITH full formatting
 *
 * Supports:
 * ✅ 100K+ rows with low memory
 * ✅ Bold headers with background colors
 * ✅ Column number formats (currency, dates, percentages)
 * ✅ Column widths
 * ✅ Freeze panes
 * ✅ Auto-filter
 *
 * Usage:
 *   class LargeStyledExport implements FromQuery, UseHybridExporter
 *   {
 *       public function useHybridExporter(): bool
 *       {
 *           return true;
 *       }
 *   }
 */
interface UseHybridExporter
{
    /**
     * Whether to use the hybrid OpenSpout + PhpSpreadsheet exporter.
     */
    public function useHybridExporter(): bool;
}
