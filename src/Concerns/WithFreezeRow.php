<?php

namespace LaravelExporter\Concerns;

/**
 * Freeze the header row.
 */
interface WithFreezeRow
{
    /**
     * @return string|null Cell to freeze at (e.g., 'A2' for freezing first row)
     */
    public function freezePane(): ?string;
}
