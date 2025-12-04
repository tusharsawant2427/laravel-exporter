<?php

namespace LaravelExporter\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Use an Eloquent query to populate the export.
 */
interface FromQuery
{
    /**
     * @return Builder
     */
    public function query(): Builder;
}
