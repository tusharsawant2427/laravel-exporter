<?php

namespace LaravelExporter\Concerns;

/**
 * Format the row before it's written to the file.
 */
interface WithMapping
{
    /**
     * @param mixed $row
     * @return array
     */
    public function map($row): array;
}
