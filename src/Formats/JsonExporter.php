<?php

namespace LaravelExporter\Formats;

use Generator;
use LaravelExporter\Contracts\FormatExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JsonExporter implements FormatExporterInterface
{
    protected bool $prettyPrint = false;
    protected bool $wrapInObject = false;
    protected string $dataKey = 'data';
    protected bool $includeMetadata = false;

    public function __construct(array $options = [])
    {
        $this->prettyPrint = $options['pretty_print'] ?? $this->prettyPrint;
        $this->wrapInObject = $options['wrap_in_object'] ?? $this->wrapInObject;
        $this->dataKey = $options['data_key'] ?? $this->dataKey;
        $this->includeMetadata = $options['include_metadata'] ?? $this->includeMetadata;
    }

    public function export(Generator $data, array $headers, string $path): bool
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open file for writing: {$path}");
        }

        try {
            $this->writeJsonContent($handle, $data, $headers);
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
        $this->writeJsonContent($handle, $data, $headers);
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
            $this->writeJsonContent($handle, $data, $headers);
            fclose($handle);
        }, 200, [
            'Content-Type' => $this->getContentType(),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    protected function writeJsonContent($handle, Generator $data, array $headers): void
    {
        $flags = $this->prettyPrint ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE : JSON_UNESCAPED_UNICODE;
        $indent = $this->prettyPrint ? '    ' : '';
        $newline = $this->prettyPrint ? "\n" : '';
        $rowCount = 0;

        if ($this->wrapInObject) {
            fwrite($handle, '{' . $newline);

            if ($this->includeMetadata) {
                fwrite($handle, $indent . '"exported_at": "' . date('c') . '",' . $newline);
                fwrite($handle, $indent . '"headers": ' . json_encode($headers, $flags) . ',' . $newline);
            }

            fwrite($handle, $indent . '"' . $this->dataKey . '": [' . $newline);
        } else {
            fwrite($handle, '[' . $newline);
        }

        $first = true;
        foreach ($data as $row) {
            if (!$first) {
                fwrite($handle, ',' . $newline);
            }
            $first = false;
            $rowCount++;

            $prefix = $this->wrapInObject ? $indent . $indent : $indent;
            fwrite($handle, $prefix . json_encode($row, $flags));

            // Flush periodically for large datasets
            if ($rowCount % 1000 === 0) {
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }

        if ($this->wrapInObject) {
            fwrite($handle, $newline . $indent . ']');

            if ($this->includeMetadata) {
                fwrite($handle, ',' . $newline . $indent . '"total_records": ' . $rowCount);
            }

            fwrite($handle, $newline . '}');
        } else {
            fwrite($handle, $newline . ']');
        }
    }

    public function getExtension(): string
    {
        return 'json';
    }

    public function getContentType(): string
    {
        return 'application/json; charset=UTF-8';
    }

    protected function ensureExtension(string $filename): string
    {
        if (!str_ends_with(strtolower($filename), '.json')) {
            return $filename . '.json';
        }
        return $filename;
    }
}
