<?php

namespace LaravelExporter\Concerns;

/**
 * Format certain columns with specific number/date formats.
 */
interface WithColumnFormatting
{
    /**
     * @return array Column formats ['A' => NumberFormat::FORMAT_DATE, 'B' => '#,##0.00']
     */
    public function columnFormats(): array;
}
