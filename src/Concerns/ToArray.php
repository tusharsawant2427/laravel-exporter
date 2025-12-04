<?php

namespace LaravelExporter\Concerns;

/**
 * Import rows into an array
 *
 * Similar to Maatwebsite\Excel\Concerns\ToArray
 */
interface ToArray
{
    /**
     * Handle the imported array
     *
     * @param array $array The imported rows as an array
     * @return void
     */
    public function array(array $array): void;
}
