<?php

namespace LaravelExporter\Concerns;

use Illuminate\Support\Collection;

/**
 * Use a Laravel Collection to populate the export.
 */
interface FromCollection
{
    /**
     * @return Collection
     */
    public function collection(): Collection;
}
