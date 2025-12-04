<?php

namespace LaravelExporter\Imports;

use Illuminate\Support\Collection;

/**
 * Collection of import errors with helper methods
 */
class ImportErrors
{
    /** @var Failure[] */
    protected array $failures = [];

    /** @var array<int, \Throwable> */
    protected array $errors = [];

    /**
     * Add a validation failure
     */
    public function addFailure(Failure $failure): void
    {
        $this->failures[] = $failure;
    }

    /**
     * Add an error
     */
    public function addError(int $row, \Throwable $error): void
    {
        $this->errors[$row] = $error;
    }

    /**
     * Get all failures
     *
     * @return Failure[]
     */
    public function failures(): array
    {
        return $this->failures;
    }

    /**
     * Get all errors
     *
     * @return array<int, \Throwable>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Check if there are any failures
     */
    public function hasFailures(): bool
    {
        return count($this->failures) > 0;
    }

    /**
     * Check if there are any errors
     */
    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Check if there are any issues
     */
    public function hasIssues(): bool
    {
        return $this->hasFailures() || $this->hasErrors();
    }

    /**
     * Get count of failures
     */
    public function failureCount(): int
    {
        return count($this->failures);
    }

    /**
     * Get count of errors
     */
    public function errorCount(): int
    {
        return count($this->errors);
    }

    /**
     * Clear all failures and errors
     */
    public function clear(): void
    {
        $this->failures = [];
        $this->errors = [];
    }

    /**
     * Get failures as collection
     */
    public function failuresCollection(): Collection
    {
        return new Collection($this->failures);
    }

    /**
     * Get summary
     */
    public function summary(): array
    {
        return [
            'failures' => $this->failureCount(),
            'errors' => $this->errorCount(),
            'total_issues' => $this->failureCount() + $this->errorCount(),
        ];
    }
}
