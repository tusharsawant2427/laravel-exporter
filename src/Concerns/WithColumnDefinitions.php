<?php

namespace LaravelExporter\Concerns;

use LaravelExporter\Support\ColumnCollection;

/**
 * Define columns with types using the fluent ColumnCollection builder.
 */
interface WithColumnDefinitions
{
    /**
     * @return ColumnCollection
     */
    public function columns(): ColumnCollection;
}
