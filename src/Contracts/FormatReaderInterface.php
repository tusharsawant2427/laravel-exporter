<?php

namespace LaravelExporter\Contracts;

use Generator;

/**
 * Interface for format-specific import readers
 */
interface FormatReaderInterface
{
    /**
     * Read the file and return rows
     *
     * @param string $filePath Path to the file
     * @param array $options Reader options
     * @return Generator<int, array> Yields row number => row data
     */
    public function read(string $filePath, array $options = []): Generator;

    /**
     * Get total row count (if available)
     *
     * @param string $filePath Path to the file
     * @return int|null Row count or null if not determinable
     */
    public function getRowCount(string $filePath): ?int;

    /**
     * Get the sheets in the file (for multi-sheet files)
     *
     * @param string $filePath Path to the file
     * @return array<int, string> Sheet index => sheet name
     */
    public function getSheets(string $filePath): array;

    /**
     * Check if the reader supports the given file extension
     *
     * @param string $extension File extension without dot
     * @return bool
     */
    public function supports(string $extension): bool;
}
