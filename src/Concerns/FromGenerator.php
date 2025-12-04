<?php

namespace LaravelExporter\Concerns;

use Generator;

/**
 * Use a Generator to populate the export.
 */
interface FromGenerator
{
    /**
     * @return Generator
     */
    public function generator(): Generator;
}
