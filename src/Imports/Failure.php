<?php

namespace LaravelExporter\Imports;

/**
 * Represents a validation failure during import
 *
 * Similar to Maatwebsite\Excel\Validators\Failure
 */
class Failure
{
    protected int $row;
    protected string $attribute;
    protected array $errors;
    protected array $values;

    public function __construct(int $row, string $attribute, array $errors, array $values = [])
    {
        $this->row = $row;
        $this->attribute = $attribute;
        $this->errors = $errors;
        $this->values = $values;
    }

    /**
     * Get the row number where the failure occurred
     */
    public function row(): int
    {
        return $this->row;
    }

    /**
     * Get the attribute/column that failed validation
     */
    public function attribute(): string
    {
        return $this->attribute;
    }

    /**
     * Get the validation error messages
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get the row values that caused the failure
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'row' => $this->row,
            'attribute' => $this->attribute,
            'errors' => $this->errors,
            'values' => $this->values,
        ];
    }
}
