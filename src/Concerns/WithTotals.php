<?php

namespace LaravelExporter\Concerns;

/**
 * Add a totals/summary row at the bottom.
 */
interface WithTotals
{
    /**
     * @return array Column keys to sum
     */
    public function totalColumns(): array;

    /**
     * @return string Label for totals row
     */
    public function totalLabel(): string;
}
