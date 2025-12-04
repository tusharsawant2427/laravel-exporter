<?php

namespace LaravelExporter\Concerns;

/**
 * Set column widths.
 */
interface WithColumnWidths
{
    /**
     * @return array Column widths ['A' => 55, 'B' => 45]
     */
    public function columnWidths(): array;
}
