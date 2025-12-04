<?php

namespace LaravelExporter\Concerns;

/**
 * Set the Workbook or Worksheet title.
 */
interface WithTitle
{
    /**
     * @return string
     */
    public function title(): string;
}
