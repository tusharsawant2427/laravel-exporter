<?php

namespace LaravelExporter;

/**
 * Column Type Constants
 *
 * Defines all available column types for data exports with
 * proper formatting and styling support.
 */
class ColumnTypes
{
    /**
     * Plain text column
     */
    const STRING = 'string';

    /**
     * Whole number column
     */
    const INTEGER = 'integer';

    /**
     * Currency amount with conditional coloring (positive=green, negative=red)
     */
    const AMOUNT = 'amount';

    /**
     * Currency amount without conditional coloring
     */
    const AMOUNT_PLAIN = 'amount_plain';

    /**
     * Percentage values (stored as decimal, displayed as %)
     */
    const PERCENTAGE = 'percentage';

    /**
     * Date values
     */
    const DATE = 'date';

    /**
     * Date and time values
     */
    const DATETIME = 'datetime';

    /**
     * Boolean values (Yes/No)
     */
    const BOOLEAN = 'boolean';

    /**
     * Numeric quantities
     */
    const QUANTITY = 'quantity';

    /**
     * Get all available types
     */
    public static function all(): array
    {
        return [
            self::STRING,
            self::INTEGER,
            self::AMOUNT,
            self::AMOUNT_PLAIN,
            self::PERCENTAGE,
            self::DATE,
            self::DATETIME,
            self::BOOLEAN,
            self::QUANTITY,
        ];
    }

    /**
     * Check if a type is numeric (for totals calculation)
     */
    public static function isNumeric(string $type): bool
    {
        return in_array($type, [
            self::INTEGER,
            self::AMOUNT,
            self::AMOUNT_PLAIN,
            self::PERCENTAGE,
            self::QUANTITY,
        ]);
    }

    /**
     * Check if a type supports conditional coloring
     */
    public static function supportsColoring(string $type): bool
    {
        return in_array($type, [
            self::AMOUNT,
            self::AMOUNT_PLAIN,
        ]);
    }
}
