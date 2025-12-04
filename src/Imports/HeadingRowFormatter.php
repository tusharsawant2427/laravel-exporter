<?php

namespace LaravelExporter\Imports;

/**
 * Format heading row values into usable array keys
 *
 * Similar to Maatwebsite\Excel\HeadingRowFormatter
 */
class HeadingRowFormatter
{
    protected static string $defaultFormat = 'slug';

    /**
     * Set the default format
     */
    public static function default(string $format): void
    {
        self::$defaultFormat = $format;
    }

    /**
     * Format headings array
     */
    public static function format(array $headings, ?string $format = null): array
    {
        $format = $format ?? self::$defaultFormat;

        return array_map(function ($heading) use ($format) {
            return self::formatHeading($heading, $format);
        }, $headings);
    }

    /**
     * Format a single heading
     */
    public static function formatHeading(mixed $heading, string $format = 'slug'): string
    {
        if (!is_string($heading)) {
            $heading = (string) $heading;
        }

        return match ($format) {
            'slug' => self::toSlug($heading),
            'snake' => self::toSnakeCase($heading),
            'camel' => self::toCamelCase($heading),
            'studly', 'pascal' => self::toStudlyCase($heading),
            'none' => $heading,
            default => self::toSlug($heading),
        };
    }

    /**
     * Convert to slug format (kebab-case but with underscores)
     */
    protected static function toSlug(string $value): string
    {
        // Convert to lowercase
        $value = strtolower($value);

        // Replace spaces, dashes, and special chars with underscores
        $value = preg_replace('/[\s\-]+/', '_', $value);

        // Remove non-alphanumeric except underscores
        $value = preg_replace('/[^a-z0-9_]/', '', $value);

        // Remove multiple underscores
        $value = preg_replace('/_+/', '_', $value);

        // Trim underscores
        return trim($value, '_');
    }

    /**
     * Convert to snake_case
     */
    protected static function toSnakeCase(string $value): string
    {
        // Insert underscore before uppercase letters
        $value = preg_replace('/([a-z])([A-Z])/', '$1_$2', $value);

        // Replace spaces with underscores
        $value = preg_replace('/[\s]+/', '_', $value);

        // Remove special characters
        $value = preg_replace('/[^a-zA-Z0-9_]/', '', $value);

        // Convert to lowercase
        return strtolower($value);
    }

    /**
     * Convert to camelCase
     */
    protected static function toCamelCase(string $value): string
    {
        $studly = self::toStudlyCase($value);
        return lcfirst($studly);
    }

    /**
     * Convert to StudlyCase (PascalCase)
     */
    protected static function toStudlyCase(string $value): string
    {
        // Replace non-alphanumeric with spaces
        $value = preg_replace('/[^a-zA-Z0-9]/', ' ', $value);

        // Capitalize each word
        $value = ucwords($value);

        // Remove spaces
        return str_replace(' ', '', $value);
    }
}
