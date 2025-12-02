<?php

namespace LaravelExporter\Support;

use Illuminate\Support\Collection;

/**
 * Column Collection
 *
 * Manages a collection of column definitions with helper methods
 * for building export configurations using a fluent API.
 */
class ColumnCollection
{
    protected Collection $columns;
    protected ?string $lastAddedKey = null;

    public function __construct()
    {
        $this->columns = collect();
    }

    /**
     * Create a new column collection
     */
    public static function make(): static
    {
        return new static();
    }

    /**
     * Add a column definition
     */
    public function add(ColumnDefinition $column): static
    {
        $this->columns->put($column->getKey(), $column);
        $this->lastAddedKey = $column->getKey();
        return $this;
    }

    /**
     * Add multiple columns at once
     */
    public function addMany(array $columns): static
    {
        foreach ($columns as $column) {
            $this->add($column);
        }
        return $this;
    }

    /**
     * Add a string column
     */
    public function string(string $key, ?string $label = null): static
    {
        $column = ColumnDefinition::make($key)->string();
        if ($label) {
            $column->label($label);
        }
        return $this->add($column);
    }

    /**
     * Add an integer column
     */
    public function integer(string $key, ?string $label = null): static
    {
        $column = ColumnDefinition::make($key)->integer();
        if ($label) {
            $column->label($label);
        }
        return $this->add($column);
    }

    /**
     * Add an amount column (formatted with coloring)
     */
    public function amount(string $key, ?string $label = null): static
    {
        $column = ColumnDefinition::make($key)->amount();
        if ($label) {
            $column->label($label);
        }
        return $this->add($column);
    }

    /**
     * Add an amount column without coloring
     */
    public function amountPlain(string $key, ?string $label = null): static
    {
        $column = ColumnDefinition::make($key)->amountPlain();
        if ($label) {
            $column->label($label);
        }
        return $this->add($column);
    }

    /**
     * Add a percentage column
     */
    public function percentage(string $key, ?string $label = null): static
    {
        $column = ColumnDefinition::make($key)->percentage();
        if ($label) {
            $column->label($label);
        }
        return $this->add($column);
    }

    /**
     * Add a date column
     */
    public function date(string $key, ?string $label = null): static
    {
        $column = ColumnDefinition::make($key)->date();
        if ($label) {
            $column->label($label);
        }
        return $this->add($column);
    }

    /**
     * Add a datetime column
     */
    public function datetime(string $key, ?string $label = null): static
    {
        $column = ColumnDefinition::make($key)->datetime();
        if ($label) {
            $column->label($label);
        }
        return $this->add($column);
    }

    /**
     * Add a boolean column
     */
    public function boolean(string $key, ?string $label = null): static
    {
        $column = ColumnDefinition::make($key)->boolean();
        if ($label) {
            $column->label($label);
        }
        return $this->add($column);
    }

    /**
     * Add a quantity column
     */
    public function quantity(string $key, ?string $label = null): static
    {
        $column = ColumnDefinition::make($key)->quantity();
        if ($label) {
            $column->label($label);
        }
        return $this->add($column);
    }

    /**
     * Apply colored formatting to the last added column
     */
    public function colored(bool $colored = true): static
    {
        if ($this->lastAddedKey && $this->columns->has($this->lastAddedKey)) {
            $this->columns->get($this->lastAddedKey)->colored($colored);
        }
        return $this;
    }

    /**
     * Set width for the last added column
     */
    public function width(int $width): static
    {
        if ($this->lastAddedKey && $this->columns->has($this->lastAddedKey)) {
            $this->columns->get($this->lastAddedKey)->width($width);
        }
        return $this;
    }

    /**
     * Set alignment for the last added column
     */
    public function align(string $alignment): static
    {
        if ($this->lastAddedKey && $this->columns->has($this->lastAddedKey)) {
            $this->columns->get($this->lastAddedKey)->align($alignment);
        }
        return $this;
    }

    /**
     * Hide the last added column
     */
    public function hidden(bool $hidden = true): static
    {
        if ($this->lastAddedKey && $this->columns->has($this->lastAddedKey)) {
            $this->columns->get($this->lastAddedKey)->hidden($hidden);
        }
        return $this;
    }

    /**
     * Get column by key
     */
    public function get(string $key): ?ColumnDefinition
    {
        return $this->columns->get($key);
    }

    /**
     * Check if column exists
     */
    public function has(string $key): bool
    {
        return $this->columns->has($key);
    }

    /**
     * Remove a column
     */
    public function remove(string $key): static
    {
        $this->columns->forget($key);
        return $this;
    }

    /**
     * Get all columns (excluding hidden)
     */
    public function all(): Collection
    {
        return $this->columns->filter(fn($col) => !$col->isHidden());
    }

    /**
     * Get all columns including hidden
     */
    public function allWithHidden(): Collection
    {
        return $this->columns;
    }

    /**
     * Convert to column config array
     */
    public function toConfig(): array
    {
        return $this->all()
            ->mapWithKeys(fn($col, $key) => [$key => $col->toArray()])
            ->all();
    }

    /**
     * Get headers (labels) for visible columns
     */
    public function getHeaders(): array
    {
        return $this->all()
            ->map(fn($col) => $col->getLabel())
            ->values()
            ->all();
    }

    /**
     * Get keys for visible columns
     */
    public function getKeys(): array
    {
        return $this->all()->keys()->all();
    }

    /**
     * Get column count
     */
    public function count(): int
    {
        return $this->all()->count();
    }

    /**
     * Check if collection is empty
     */
    public function isEmpty(): bool
    {
        return $this->columns->isEmpty();
    }

    /**
     * Check if collection is not empty
     */
    public function isNotEmpty(): bool
    {
        return $this->columns->isNotEmpty();
    }
}
