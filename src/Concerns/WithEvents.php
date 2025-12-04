<?php

namespace LaravelExporter\Concerns;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Register events to hook into the export process.
 */
interface WithEvents
{
    /**
     * @return array Event handlers
     */
    public function registerEvents(): array;
}
