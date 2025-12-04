<?php

namespace LaravelExporter\Imports;

/**
 * Result of an import operation
 */
class ImportResult
{
    protected int $totalRows = 0;
    protected int $importedRows = 0;
    protected int $skippedRows = 0;
    protected int $failedRows = 0;
    protected ImportErrors $errors;
    protected float $duration = 0;
    protected int $peakMemory = 0;

    public function __construct()
    {
        $this->errors = new ImportErrors();
    }

    /**
     * Set total rows
     */
    public function setTotalRows(int $count): self
    {
        $this->totalRows = $count;
        return $this;
    }

    /**
     * Increment imported rows
     */
    public function incrementImported(int $count = 1): self
    {
        $this->importedRows += $count;
        return $this;
    }

    /**
     * Increment skipped rows
     */
    public function incrementSkipped(int $count = 1): self
    {
        $this->skippedRows += $count;
        return $this;
    }

    /**
     * Increment failed rows
     */
    public function incrementFailed(int $count = 1): self
    {
        $this->failedRows += $count;
        return $this;
    }

    /**
     * Set duration
     */
    public function setDuration(float $seconds): self
    {
        $this->duration = $seconds;
        return $this;
    }

    /**
     * Set peak memory
     */
    public function setPeakMemory(int $bytes): self
    {
        $this->peakMemory = $bytes;
        return $this;
    }

    /**
     * Get the errors collection
     */
    public function errors(): ImportErrors
    {
        return $this->errors;
    }

    // Getters
    public function totalRows(): int
    {
        return $this->totalRows;
    }

    public function importedRows(): int
    {
        return $this->importedRows;
    }

    public function skippedRows(): int
    {
        return $this->skippedRows;
    }

    public function failedRows(): int
    {
        return $this->failedRows;
    }

    public function duration(): float
    {
        return $this->duration;
    }

    public function peakMemory(): int
    {
        return $this->peakMemory;
    }

    public function peakMemoryFormatted(): string
    {
        return round($this->peakMemory / 1024 / 1024, 2) . ' MB';
    }

    /**
     * Check if import was successful (no failures)
     */
    public function isSuccessful(): bool
    {
        return !$this->errors->hasIssues();
    }

    /**
     * Get success rate as percentage
     */
    public function successRate(): float
    {
        if ($this->totalRows === 0) {
            return 100.0;
        }

        return round(($this->importedRows / $this->totalRows) * 100, 2);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'total_rows' => $this->totalRows,
            'imported_rows' => $this->importedRows,
            'skipped_rows' => $this->skippedRows,
            'failed_rows' => $this->failedRows,
            'success_rate' => $this->successRate() . '%',
            'duration' => round($this->duration, 2) . 's',
            'peak_memory' => $this->peakMemoryFormatted(),
            'has_errors' => $this->errors->hasIssues(),
            'failure_count' => $this->errors->failureCount(),
            'error_count' => $this->errors->errorCount(),
        ];
    }
}
