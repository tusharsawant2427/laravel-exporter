<?php

namespace LaravelExporter\Concerns;

/**
 * Validate rows before importing
 *
 * Similar to Maatwebsite\Excel\Concerns\WithValidation
 */
interface WithValidation
{
    /**
     * Get the validation rules for each row
     *
     * @return array<string, mixed> Laravel validation rules
     */
    public function rules(): array;

    /**
     * Get custom validation messages (optional)
     *
     * @return array<string, string>
     */
    public function customValidationMessages(): array;

    /**
     * Get custom attribute names (optional)
     *
     * @return array<string, string>
     */
    public function customValidationAttributes(): array;
}
