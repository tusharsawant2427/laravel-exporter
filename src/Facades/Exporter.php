<?php

namespace LaravelExporter\Facades;

use Illuminate\Support\Facades\Facade;
use LaravelExporter\Exporter as ExporterService;

/**
 * @method static ExporterService make()
 * @method static ExporterService headers(array $headers)
 * @method static ExporterService columns(array $columns)
 * @method static ExporterService transformRow(\Closure $callback)
 * @method static ExporterService chunkSize(int $size)
 * @method static ExporterService format(string $format)
 * @method static ExporterService filename(string $filename)
 * @method static ExporterService options(array $options)
 * @method static ExporterService asCsv()
 * @method static ExporterService asExcel()
 * @method static ExporterService asJson()
 * @method static \LaravelExporter\DataExporter from($source)
 *
 * @see \LaravelExporter\Exporter
 */
class Exporter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ExporterService::class;
    }
}
