<?php

namespace LaravelExporter\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Import each row as a Model
 *
 * Similar to Maatwebsite\Excel\Concerns\ToModel
 */
interface ToModel
{
    /**
     * Convert a row to a Model instance
     *
     * @param array $row The row data (keyed by column index or heading)
     * @return Model|Model[]|null Return model(s) to be saved, or null to skip
     */
    public function model(array $row): Model|array|null;
}
