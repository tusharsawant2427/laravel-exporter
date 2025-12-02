<?php

namespace LaravelExporter\Traits;

use LaravelExporter\Exporter;
use LaravelExporter\DataExporter;

/**
 * Trait to add export functionality to Eloquent models
 *
 * Usage:
 * class User extends Model
 * {
 *     use Exportable;
 *
 *     protected array $exportable = ['id', 'name', 'email']; // Optional: define exportable columns
 *     protected array $exportHeaders = ['ID', 'Full Name', 'Email Address']; // Optional: custom headers
 * }
 *
 * // Then use:
 * User::query()->export()->toFile('users.csv');
 * User::where('active', true)->export()->download('active-users.csv');
 */
trait Exportable
{
    /**
     * Get an exporter instance for the query
     */
    public function scopeExport($query, ?array $columns = null, ?array $headers = null): DataExporter
    {
        $exportService = Exporter::make();

        // Use provided columns, model's exportable columns, or all columns
        $columns = $columns ?? $this->getExportableColumns();
        if (!empty($columns)) {
            $exportService->columns($columns);
        }

        // Use provided headers or model's export headers
        $headers = $headers ?? $this->getExportHeaders();
        if (!empty($headers)) {
            $exportService->headers($headers);
        }

        return $exportService->from($query);
    }

    /**
     * Get exportable columns defined on the model
     */
    protected function getExportableColumns(): array
    {
        return property_exists($this, 'exportable') ? $this->exportable : [];
    }

    /**
     * Get export headers defined on the model
     */
    protected function getExportHeaders(): array
    {
        return property_exists($this, 'exportHeaders') ? $this->exportHeaders : [];
    }

    /**
     * Static method to quickly export all records
     */
    public static function exportAll(string $format = 'csv', ?string $path = null): bool|string
    {
        $exporter = static::query()->export();

        if ($path) {
            return Exporter::make()
                ->format($format)
                ->from(static::query())
                ->toFile($path);
        }

        return Exporter::make()
            ->format($format)
            ->from(static::query())
            ->toString();
    }
}
