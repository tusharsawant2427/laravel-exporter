<?php

namespace LaravelExporter\Concerns;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Allows styling columns, cells and rows.
 */
interface WithStyles
{
    /**
     * @param Worksheet $sheet
     * @return array|void
     */
    public function styles(Worksheet $sheet);
}
