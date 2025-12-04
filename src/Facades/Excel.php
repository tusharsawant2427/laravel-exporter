<?php

namespace LaravelExporter\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Excel Facade
 *
 * Export Methods:
 * @method static \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\StreamedResponse download(object $export, string $filename, ?string $writerType = null)
 * @method static bool store(object $export, string $path, ?string $disk = null, ?string $writerType = null)
 * @method static mixed queue(object $export, string $path, ?string $disk = null, ?string $writerType = null)
 * @method static string raw(object $export, ?string $writerType = null)
 *
 * Import Methods:
 * @method static \LaravelExporter\Imports\ImportResult import(object $import, string $filePath, ?string $disk = null, ?string $readerType = null)
 * @method static array toArray(object $import, string $filePath, ?string $disk = null, ?string $readerType = null)
 * @method static \Illuminate\Support\Collection toCollection(object $import, string $filePath, ?string $disk = null, ?string $readerType = null)
 * @method static \LaravelExporter\Imports\ImportResult queueImport(object $import, string $filePath, ?string $disk = null, ?string $readerType = null)
 *
 * @see \LaravelExporter\Excel
 */
class Excel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-exporter';
    }
}
