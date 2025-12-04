<?php

namespace LaravelExporter\Concerns;

/**
 * Read calculated formula values instead of formula strings
 *
 * Similar to Maatwebsite\Excel\Concerns\WithCalculatedFormulas
 */
interface WithCalculatedFormulas
{
    // Marker interface - no methods required
    // When implemented, formulas will be evaluated and their results returned
}
