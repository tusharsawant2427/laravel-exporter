<?php

namespace LaravelExporter\Concerns;

/**
 * Enable multi-sheet support.
 */
interface WithMultipleSheets
{
    /**
     * @return array Array of sheet export classes
     */
    public function sheets(): array;
}
