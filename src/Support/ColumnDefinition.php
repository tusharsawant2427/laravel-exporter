<?php

namespace LaravelExporter\Support;

use LaravelExporter\ColumnTypes;

/**
 * Fluent Column Definition Builder
 *
 * Provides a fluent interface for defining export columns
 * with proper type hints and IDE autocompletion support.
 */
class ColumnDefinition
{
    protected string $key;
    protected string $label;
    protected string $type = ColumnTypes::STRING;
    protected ?int $width = null;
    protected bool $colorConditional = false;
    protected ?string $dateFormat = null;
    protected ?int $decimalPlaces = null;
    protected mixed $transformer = null;
    protected bool $hidden = false;
    protected ?string $alignment = null;
    protected ?string $formula = null;
    protected ?string $numberFormat = null;

    /**
     * Conditional cell styles
     * @var array<array{condition: callable, style: CellStyle|callable}>
     */
    protected array $conditionalStyles = [];

    public function __construct(string $key)
    {
        $this->key = $key;
        $this->label = $this->generateLabel($key);
    }

    /**
     * Create a new column definition
     */
    public static function make(string $key): static
    {
        return new static($key);
    }

    /**
     * Set the column label/heading
     */
    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Set column type to string
     */
    public function string(): static
    {
        $this->type = ColumnTypes::STRING;
        return $this;
    }

    /**
     * Set column type to integer
     */
    public function integer(): static
    {
        $this->type = ColumnTypes::INTEGER;
        return $this;
    }

    /**
     * Set column type to amount (formatted with conditional coloring)
     */
    public function amount(): static
    {
        $this->type = ColumnTypes::AMOUNT;
        $this->colorConditional = true;
        return $this;
    }

    /**
     * Set column type to amount without conditional coloring
     */
    public function amountPlain(): static
    {
        $this->type = ColumnTypes::AMOUNT_PLAIN;
        $this->colorConditional = false;
        return $this;
    }

    /**
     * Set column type to percentage
     */
    public function percentage(): static
    {
        $this->type = ColumnTypes::PERCENTAGE;
        return $this;
    }

    /**
     * Set column type to date
     */
    public function date(?string $format = null): static
    {
        $this->type = ColumnTypes::DATE;
        if ($format) {
            $this->dateFormat = $format;
        }
        return $this;
    }

    /**
     * Set column type to datetime
     */
    public function datetime(?string $format = null): static
    {
        $this->type = ColumnTypes::DATETIME;
        if ($format) {
            $this->dateFormat = $format;
        }
        return $this;
    }

    /**
     * Set column type to boolean
     */
    public function boolean(): static
    {
        $this->type = ColumnTypes::BOOLEAN;
        return $this;
    }

    /**
     * Set column type to quantity
     */
    public function quantity(): static
    {
        $this->type = ColumnTypes::QUANTITY;
        return $this;
    }

    /**
     * Set custom column width
     */
    public function width(int $width): static
    {
        $this->width = $width;
        return $this;
    }

    /**
     * Enable/disable conditional coloring for amount columns
     */
    public function colored(bool $colored = true): static
    {
        $this->colorConditional = $colored;
        return $this;
    }

    /**
     * Set decimal places for numeric columns
     */
    public function decimals(int $places): static
    {
        $this->decimalPlaces = $places;
        return $this;
    }

    /**
     * Set a value transformer callback
     */
    public function transform(callable $callback): static
    {
        $this->transformer = $callback;
        return $this;
    }

    /**
     * Mark column as hidden (won't be exported)
     */
    public function hidden(bool $hidden = true): static
    {
        $this->hidden = $hidden;
        return $this;
    }

    /**
     * Set column alignment
     */
    public function align(string $alignment): static
    {
        $this->alignment = $alignment;
        return $this;
    }

    /**
     * Align left
     */
    public function alignLeft(): static
    {
        return $this->align('left');
    }

    /**
     * Align center
     */
    public function alignCenter(): static
    {
        return $this->align('center');
    }

    /**
     * Align right
     */
    public function alignRight(): static
    {
        return $this->align('right');
    }

    /**
     * Set Excel formula
     */
    public function formula(string $formula): static
    {
        $this->formula = $formula;
        return $this;
    }

    /**
     * Set custom number format
     */
    public function numberFormat(string $format): static
    {
        $this->numberFormat = $format;
        return $this;
    }

    /**
     * Add conditional styling based on cell value or row data
     *
     * @param callable $condition Function that receives ($value, $row) and returns bool
     * @param CellStyle|callable $style CellStyle or callback that returns CellStyle
     *
     * Examples:
     * ->when(fn($value) => $value > 1000, CellStyle::make()->green()->bold())
     * ->when(fn($value, $row) => $row['status'] === 'cancelled', CellStyle::make()->red())
     * ->when(fn($value) => $value < 0, fn() => CellStyle::make()->danger())
     */
    public function when(callable $condition, CellStyle|callable $style): static
    {
        $this->conditionalStyles[] = [
            'condition' => $condition,
            'style' => $style,
        ];
        return $this;
    }

    /**
     * Style when value equals a specific value
     */
    public function whenEquals(mixed $compareValue, CellStyle|callable $style): static
    {
        return $this->when(
            fn($value) => $value === $compareValue,
            $style
        );
    }

    /**
     * Style when value is in array of values
     */
    public function whenIn(array $values, CellStyle|callable $style): static
    {
        return $this->when(
            fn($value) => in_array($value, $values),
            $style
        );
    }

    /**
     * Style when value is greater than threshold
     */
    public function whenGreaterThan(float|int $threshold, CellStyle|callable $style): static
    {
        return $this->when(
            fn($value) => is_numeric($value) && $value > $threshold,
            $style
        );
    }

    /**
     * Style when value is less than threshold
     */
    public function whenLessThan(float|int $threshold, CellStyle|callable $style): static
    {
        return $this->when(
            fn($value) => is_numeric($value) && $value < $threshold,
            $style
        );
    }

    /**
     * Style when value is between min and max (inclusive)
     */
    public function whenBetween(float|int $min, float|int $max, CellStyle|callable $style): static
    {
        return $this->when(
            fn($value) => is_numeric($value) && $value >= $min && $value <= $max,
            $style
        );
    }

    /**
     * Style when value is empty/null/zero
     */
    public function whenEmpty(CellStyle|callable $style): static
    {
        return $this->when(
            fn($value) => empty($value) || $value === 0 || $value === '0',
            $style
        );
    }

    /**
     * Style when value is not empty
     */
    public function whenNotEmpty(CellStyle|callable $style): static
    {
        return $this->when(
            fn($value) => !empty($value) && $value !== 0 && $value !== '0',
            $style
        );
    }

    /**
     * Style when value contains a string
     */
    public function whenContains(string $needle, CellStyle|callable $style): static
    {
        return $this->when(
            fn($value) => is_string($value) && str_contains(strtolower($value), strtolower($needle)),
            $style
        );
    }

    /**
     * Get conditional styles
     */
    public function getConditionalStyles(): array
    {
        return $this->conditionalStyles;
    }

    /**
     * Check if column has conditional styles
     */
    public function hasConditionalStyles(): bool
    {
        return !empty($this->conditionalStyles);
    }

    /**
     * Get the style for a specific value and row
     */
    public function getStyleForValue(mixed $value, array $row = []): ?CellStyle
    {
        foreach ($this->conditionalStyles as $conditional) {
            $condition = $conditional['condition'];
            $style = $conditional['style'];

            if ($condition($value, $row)) {
                return is_callable($style) ? $style($value, $row) : $style;
            }
        }
        return null;
    }

    /**
     * Convert to array for column config
     */
    public function toArray(): array
    {
        $config = [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'color_conditional' => $this->colorConditional,
        ];

        if ($this->width !== null) {
            $config['width'] = $this->width;
        }

        if ($this->dateFormat !== null) {
            $config['date_format'] = $this->dateFormat;
        }

        if ($this->decimalPlaces !== null) {
            $config['decimal_places'] = $this->decimalPlaces;
        }

        if ($this->transformer !== null) {
            $config['transformer'] = $this->transformer;
        }

        if ($this->hidden) {
            $config['hidden'] = true;
        }

        if ($this->alignment !== null) {
            $config['alignment'] = $this->alignment;
        }

        if ($this->formula !== null) {
            $config['formula'] = $this->formula;
        }

        if ($this->numberFormat !== null) {
            $config['number_format'] = $this->numberFormat;
        }

        if (!empty($this->conditionalStyles)) {
            $config['conditional_styles'] = $this->conditionalStyles;
            $config['column_definition'] = $this; // Pass reference for style resolution
        }

        return $config;
    }

    /**
     * Get the column key
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get the column label
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get the column type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Check if column has conditional coloring
     */
    public function hasConditionalColoring(): bool
    {
        return $this->colorConditional;
    }

    /**
     * Check if column is hidden
     */
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * Get transformer
     */
    public function getTransformer(): mixed
    {
        return $this->transformer;
    }

    /**
     * Generate a label from column key
     */
    protected function generateLabel(string $key): string
    {
        // Handle dot notation (e.g., 'customer.name' -> 'Customer Name')
        $key = str_replace('.', ' ', $key);

        // Convert snake_case and camelCase to Title Case
        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
        $label = str_replace('_', ' ', $label);

        return ucwords(strtolower($label));
    }
}
