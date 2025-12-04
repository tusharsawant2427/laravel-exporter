<?php

namespace LaravelExporter\Concerns;

use Illuminate\Support\Collection;

/**
 * Import rows into a Collection
 *
 * Similar to Maatwebsite\Excel\Concerns\ToCollection
 */
interface ToCollection
{
    /**
     * Handle the imported collection
     *
     * @param Collection $collection The imported rows as a collection
     * @return void
     */
    public function collection(Collection $collection): void;
}
