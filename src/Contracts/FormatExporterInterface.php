<?php

namespace LaravelExporter\Contracts;

use Generator;

interface FormatExporterInterface
{
    /**
     * Export data to a file
     */
    public function export(Generator $data, array $headers, string $path): bool;

    /**
     * Export data and return as downloadable response
     */
    public function download(Generator $data, array $headers, string $filename): mixed;

    /**
     * Export data to string
     */
    public function toString(Generator $data, array $headers): string;

    /**
     * Stream the export (memory efficient)
     */
    public function stream(Generator $data, array $headers, string $filename): mixed;

    /**
     * Get the file extension for this format
     */
    public function getExtension(): string;

    /**
     * Get the content type for this format
     */
    public function getContentType(): string;
}
