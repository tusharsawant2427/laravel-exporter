<?php

namespace LaravelExporter\Concerns;

/**
 * Enable auto-filter on columns.
 */
interface WithAutoFilter
{
    /**
     * @return string|null Range for auto-filter (e.g., 'A1:F100') or null for auto-detect
     */
    public function autoFilter(): ?string;
}
