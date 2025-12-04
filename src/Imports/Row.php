<?php

namespace LaravelExporter\Imports;

use ArrayAccess;
use Illuminate\Support\Collection;

/**
 * Represents a single row during import
 *
 * Similar to Maatwebsite\Excel\Row
 */
class Row implements ArrayAccess
{
    protected int $rowNumber;
    protected array $data;
    protected ?array $headings;

    public function __construct(int $rowNumber, array $data, ?array $headings = null)
    {
        $this->rowNumber = $rowNumber;
        $this->data = $data;
        $this->headings = $headings;
    }

    /**
     * Get the row number (1-indexed)
     */
    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }

    /**
     * Get the row index (0-indexed)
     */
    public function getIndex(): int
    {
        return $this->rowNumber - 1;
    }

    /**
     * Get the raw row data as array
     */
    public function toArray(): array
    {
        if ($this->headings) {
            return array_combine($this->headings, array_pad($this->data, count($this->headings), null));
        }

        return $this->data;
    }

    /**
     * Get the row data as Collection
     */
    public function toCollection(): Collection
    {
        return new Collection($this->toArray());
    }

    /**
     * Get a specific column value by index or heading name
     */
    public function get(int|string $key, mixed $default = null): mixed
    {
        if (is_string($key) && $this->headings) {
            $index = array_search($key, $this->headings);
            if ($index !== false) {
                return $this->data[$index] ?? $default;
            }
        }

        return $this->data[$key] ?? $default;
    }

    /**
     * Check if a column exists
     */
    public function has(int|string $key): bool
    {
        if (is_string($key) && $this->headings) {
            return in_array($key, $this->headings);
        }

        return isset($this->data[$key]);
    }

    /**
     * Check if the row is empty
     */
    public function isEmpty(): bool
    {
        return empty(array_filter($this->data, fn($value) => $value !== null && $value !== ''));
    }

    // ArrayAccess implementation
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_string($offset) && $this->headings) {
            $index = array_search($offset, $this->headings);
            if ($index !== false) {
                $this->data[$index] = $value;
                return;
            }
        }

        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        if (is_string($offset) && $this->headings) {
            $index = array_search($offset, $this->headings);
            if ($index !== false) {
                unset($this->data[$index]);
                return;
            }
        }

        unset($this->data[$offset]);
    }

    /**
     * Get all values
     */
    public function all(): array
    {
        return $this->toArray();
    }

    /**
     * Get only specific keys
     */
    public function only(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    /**
     * Get all except specific keys
     */
    public function except(array $keys): array
    {
        $data = $this->toArray();
        foreach ($keys as $key) {
            unset($data[$key]);
        }
        return $data;
    }
}
