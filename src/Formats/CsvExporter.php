<?php

namespace LaravelExporter\Formats;

use Generator;
use LaravelExporter\Contracts\FormatExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExporter implements FormatExporterInterface
{
    protected string $delimiter = ',';
    protected string $enclosure = '"';
    protected string $escape = '\\';
    protected bool $includeHeaders = true;
    protected ?string $encoding = 'UTF-8';
    protected bool $addBom = true;

    public function __construct(array $options = [])
    {
        $this->delimiter = $options['delimiter'] ?? $this->delimiter;
        $this->enclosure = $options['enclosure'] ?? $this->enclosure;
        $this->escape = $options['escape'] ?? $this->escape;
        $this->includeHeaders = $options['include_headers'] ?? $this->includeHeaders;
        $this->encoding = $options['encoding'] ?? $this->encoding;
        $this->addBom = $options['add_bom'] ?? $this->addBom;
    }

    public function export(Generator $data, array $headers, string $path): bool
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open file for writing: {$path}");
        }

        try {
            // Add BOM for UTF-8 Excel compatibility
            if ($this->addBom && $this->encoding === 'UTF-8') {
                fwrite($handle, "\xEF\xBB\xBF");
            }

            // Write headers
            if ($this->includeHeaders && !empty($headers)) {
                fputcsv($handle, $headers, $this->delimiter, $this->enclosure, $this->escape);
            }

            // Write data rows
            foreach ($data as $row) {
                fputcsv($handle, array_values($row), $this->delimiter, $this->enclosure, $this->escape);
            }

            return true;
        } finally {
            fclose($handle);
        }
    }

    public function download(Generator $data, array $headers, string $filename): mixed
    {
        return $this->stream($data, $headers, $filename);
    }

    public function toString(Generator $data, array $headers): string
    {
        $handle = fopen('php://temp', 'r+');

        // Add BOM for UTF-8
        if ($this->addBom && $this->encoding === 'UTF-8') {
            fwrite($handle, "\xEF\xBB\xBF");
        }

        // Write headers
        if ($this->includeHeaders && !empty($headers)) {
            fputcsv($handle, $headers, $this->delimiter, $this->enclosure, $this->escape);
        }

        // Write data rows
        foreach ($data as $row) {
            fputcsv($handle, array_values($row), $this->delimiter, $this->enclosure, $this->escape);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    public function stream(Generator $data, array $headers, string $filename): mixed
    {
        $filename = $this->ensureExtension($filename);

        return new StreamedResponse(function () use ($data, $headers) {
            $handle = fopen('php://output', 'w');

            // Add BOM for UTF-8 Excel compatibility
            if ($this->addBom && $this->encoding === 'UTF-8') {
                fwrite($handle, "\xEF\xBB\xBF");
            }

            // Write headers
            if ($this->includeHeaders && !empty($headers)) {
                fputcsv($handle, $headers, $this->delimiter, $this->enclosure, $this->escape);
            }

            // Write data rows (streamed)
            foreach ($data as $row) {
                fputcsv($handle, array_values($row), $this->delimiter, $this->enclosure, $this->escape);

                // Flush output buffer periodically for large datasets
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => $this->getContentType(),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
            'Pragma' => 'public',
        ]);
    }

    public function getExtension(): string
    {
        return 'csv';
    }

    public function getContentType(): string
    {
        return 'text/csv; charset=' . $this->encoding;
    }

    protected function ensureExtension(string $filename): string
    {
        if (!str_ends_with(strtolower($filename), '.csv')) {
            return $filename . '.csv';
        }
        return $filename;
    }
}
