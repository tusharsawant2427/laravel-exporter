<?php

namespace LaravelExporter\Concerns;

/**
 * Show a progress bar during import (for console commands)
 *
 * Similar to Maatwebsite\Excel\Concerns\WithProgressBar
 */
interface WithProgressBar
{
    // Marker interface - no methods required
    // The importer will detect this and show progress
}
