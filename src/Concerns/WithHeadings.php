<?php

namespace LaravelExporter\Concerns;

/**
 * Prepend a heading row with column labels.
 */
interface WithHeadings
{
    /**
     * @return array
     */
    public function headings(): array;
}
