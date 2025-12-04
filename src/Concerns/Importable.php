<?php

namespace LaravelExporter\Concerns;

use LaravelExporter\Facades\Excel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Make an import class self-importable
 *
 * Similar to Maatwebsite\Excel\Concerns\Importable
 */
trait Importable
{
    /**
     * Import from a file path
     */
    public function import(string $filePath, ?string $disk = null, ?string $readerType = null): mixed
    {
        return Excel::import($this, $filePath, $disk, $readerType);
    }

    /**
     * Import from an uploaded file
     */
    public function importFromUpload(UploadedFile $file, ?string $readerType = null): mixed
    {
        return Excel::import($this, $file->getRealPath(), null, $readerType);
    }

    /**
     * Convert to array
     */
    public function toArray(string $filePath, ?string $disk = null, ?string $readerType = null): array
    {
        return Excel::toArray($this, $filePath, $disk, $readerType);
    }

    /**
     * Convert to collection
     */
    public function toCollection(string $filePath, ?string $disk = null, ?string $readerType = null): Collection
    {
        return Excel::toCollection($this, $filePath, $disk, $readerType);
    }

    /**
     * Queue the import
     */
    public function queue(string $filePath, ?string $disk = null, ?string $readerType = null): mixed
    {
        return Excel::queueImport($this, $filePath, $disk, $readerType);
    }
}
