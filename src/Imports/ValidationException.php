<?php

namespace LaravelExporter\Imports;

use Exception;
use Illuminate\Support\Collection;

/**
 * Exception thrown when validation failures occur during import
 *
 * Similar to Maatwebsite\Excel\Validators\ValidationException
 */
class ValidationException extends Exception
{
    /** @var Failure[] */
    protected array $failures;

    /**
     * @param Failure[] $failures
     */
    public function __construct(array $failures, string $message = 'The given data was invalid.')
    {
        parent::__construct($message);
        $this->failures = $failures;
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
     * Get failures as a collection
     */
    public function failuresCollection(): Collection
    {
        return new Collection($this->failures);
    }

    /**
     * Get error messages grouped by row
     */
    public function errorsByRow(): array
    {
        $errors = [];
        foreach ($this->failures as $failure) {
            $row = $failure->row();
            if (!isset($errors[$row])) {
                $errors[$row] = [];
            }
            $errors[$row][$failure->attribute()] = $failure->errors();
        }
        return $errors;
    }

    /**
     * Get all error messages as a flat array
     */
    public function allErrors(): array
    {
        $errors = [];
        foreach ($this->failures as $failure) {
            foreach ($failure->errors() as $error) {
                $errors[] = "Row {$failure->row()}: {$error}";
            }
        }
        return $errors;
    }

    /**
     * Get count of failures
     */
    public function count(): int
    {
        return count($this->failures);
    }
}
