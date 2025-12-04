<?php

namespace LaravelExporter\Concerns;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Add download/store abilities right on the export class itself.
 *
 * Use this trait in your export class to enable:
 * - (new UsersExport)->download('users.xlsx')
 * - (new UsersExport)->store('exports/users.xlsx')
 */
trait Exportable
{
    /**
     * Download the export file
     *
     * @param string $filename
     * @param string|null $writerType xlsx, csv, json
     * @return BinaryFileResponse|StreamedResponse
     */
    public function download(string $filename, ?string $writerType = null)
    {
        return app('laravel-exporter')->download($this, $filename, $writerType);
    }

    /**
     * Store the export file to disk
     *
     * @param string $path
     * @param string|null $disk
     * @param string|null $writerType
     * @return bool
     */
    public function store(string $path, ?string $disk = null, ?string $writerType = null): bool
    {
        return app('laravel-exporter')->store($this, $path, $disk, $writerType);
    }

    /**
     * Queue the export
     *
     * @param string $path
     * @param string|null $disk
     * @param string|null $writerType
     * @return mixed
     */
    public function queue(string $path, ?string $disk = null, ?string $writerType = null)
    {
        return app('laravel-exporter')->queue($this, $path, $disk, $writerType);
    }

    /**
     * Export to raw content
     *
     * @param string|null $writerType
     * @return string
     */
    public function raw(?string $writerType = null): string
    {
        return app('laravel-exporter')->raw($this, $writerType);
    }
}
