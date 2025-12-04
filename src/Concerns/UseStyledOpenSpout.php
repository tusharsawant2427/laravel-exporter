<?php

namespace LaravelExporter\Concerns;

/**
 * UseStyledOpenSpout Concern
 *
 * Implement this interface to use the StyledOpenSpoutExporter
 * which provides streaming writes with basic styling support.
 *
 * Memory usage: ~50MB for 100K+ rows (vs 256MB+ for PhpSpreadsheet)
 *
 * Supported styles:
 * - Bold headers with background color
 * - Font colors
 * - Numeric cell detection
 *
 * NOT supported (use PhpSpreadsheet for these):
 * - Column number formats (currency, dates)
 * - Conditional formatting
 * - Cell formulas
 * - Cell merging
 *
 * Usage:
 *   class LargeStyledExport implements FromQuery, UseStyledOpenSpout
 *   {
 *       public function useStyledOpenSpout(): bool
 *       {
 *           return true;
 *       }
 *   }
 */
interface UseStyledOpenSpout
{
    /**
     * Whether to use the StyledOpenSpout writer.
     *
     * @return bool
     */
    public function useStyledOpenSpout(): bool;
}
