<?php

namespace LaravelExporter\Concerns;

/**
 * Use an array to populate the export.
 */
interface FromArray
{
    /**
     * @return array
     */
    public function array(): array;
}
