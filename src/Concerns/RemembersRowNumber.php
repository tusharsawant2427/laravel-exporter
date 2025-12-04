<?php

namespace LaravelExporter\Concerns;

/**
 * Remember the current row number during import
 *
 * Similar to Maatwebsite\Excel\Concerns\RemembersRowNumber
 */
trait RemembersRowNumber
{
    protected int $rowNumber = 0;

    /**
     * Set the current row number
     */
    public function setRowNumber(int $rowNumber): void
    {
        $this->rowNumber = $rowNumber;
    }

    /**
     * Get the current row number
     */
    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }
}
