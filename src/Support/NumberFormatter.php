<?php

namespace LaravelExporter\Support;

use LaravelExporter\ColumnTypes;

/**
 * Number Formatter
 *
 * Provides formatting utilities for numbers, currencies,
 * and locale-specific number formats.
 */
class NumberFormatter
{
    /**
     * Default locale for formatting
     */
    protected string $locale = 'en_US';

    /**
     * Currency symbol
     */
    protected string $currencySymbol = '$';

    /**
     * Decimal places for amounts
     */
    protected int $decimalPlaces = 2;

    public function __construct(array $config = [])
    {
        $this->locale = $config['locale'] ?? $this->locale;
        $this->currencySymbol = $config['currency_symbol'] ?? $this->currencySymbol;
        $this->decimalPlaces = $config['decimal_places'] ?? $this->decimalPlaces;
    }

    /**
     * Format number with locale-specific numbering system
     *
     * @param float $number The number to format
     * @param int $decimals Number of decimal places
     * @param string|null $locale Locale code (e.g., 'en_US', 'en_IN', 'de_DE')
     */
    public function formatWithLocale(float $number, int $decimals = 2, ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;
        $locales = config('exporter.locale.locales', []);
        $localeConfig = $locales[$locale] ?? [];

        $isNegative = $number < 0;
        $number = abs($number);

        // Special handling for Indian numbering (en_IN)
        if ($locale === 'en_IN') {
            return $this->formatIndianStyle($number, $decimals, $isNegative);
        }

        // Standard formatting for other locales
        $thousandSep = $localeConfig['thousand_separator'] ?? ',';
        $decimalSep = $localeConfig['decimal_separator'] ?? '.';

        $formatted = number_format($number, $decimals, $decimalSep, $thousandSep);

        return $isNegative ? '-' . $formatted : $formatted;
    }

    /**
     * Format number with Indian numbering system (12,34,567.00)
     */
    protected function formatIndianStyle(float $number, int $decimals, bool $isNegative): string
    {
        $formatted = number_format($number, $decimals);

        $parts = explode('.', $formatted);
        $intPart = str_replace(',', '', $parts[0]);
        $decPart = $parts[1] ?? str_repeat('0', $decimals);

        // Indian numbering: last 3 digits, then groups of 2
        if (strlen($intPart) > 3) {
            $lastThree = substr($intPart, -3);
            $remaining = substr($intPart, 0, -3);
            $remaining = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $remaining);
            $intPart = $remaining . ',' . $lastThree;
        }

        $result = $intPart;
        if ($decimals > 0) {
            $result .= '.' . $decPart;
        }

        return $isNegative ? '-' . $result : $result;
    }

    /**
     * Format as currency with locale-specific formatting
     *
     * @param float $amount The amount to format
     * @param bool $includeSymbol Whether to include currency symbol
     * @param string|null $locale Locale code
     */
    public function formatCurrency(float $amount, bool $includeSymbol = true, ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;
        $formatted = $this->formatWithLocale($amount, $this->decimalPlaces, $locale);

        if ($includeSymbol) {
            $symbol = $this->getCurrencySymbol($locale);
            return $symbol . $formatted;
        }

        return $formatted;
    }

    /**
     * @deprecated Use formatWithLocale() with 'en_IN' locale instead
     */
    public function formatIndian(float $number, int $decimals = 2): string
    {
        return $this->formatWithLocale($number, $decimals, 'en_IN');
    }

    /**
     * @deprecated Use formatCurrency() with 'en_IN' locale instead
     */
    public function formatINR(float $amount, bool $includeSymbol = true): string
    {
        return $this->formatCurrency($amount, $includeSymbol, 'en_IN');
    }

    /**
     * Format as standard number (1,234,567.00)
     */
    public function formatStandard(float $number, int $decimals = 2): string
    {
        return number_format($number, $decimals);
    }

    /**
     * Format as percentage
     */
    public function formatPercentage(float $number, int $decimals = 2): string
    {
        return number_format($number * 100, $decimals) . '%';
    }

    /**
     * Format based on column type
     */
    public function formatByType(mixed $value, string $type, array $options = []): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            ColumnTypes::AMOUNT, ColumnTypes::AMOUNT_PLAIN => (float) $value,
            ColumnTypes::INTEGER => (int) $value,
            ColumnTypes::QUANTITY => (float) $value,
            ColumnTypes::PERCENTAGE => (float) $value / 100, // Store as decimal
            ColumnTypes::BOOLEAN => $value ? 'Yes' : 'No',
            ColumnTypes::DATE => $this->formatDate($value, $options['date_format'] ?? 'd-M-Y'),
            ColumnTypes::DATETIME => $this->formatDateTime($value, $options['date_format'] ?? 'd-M-Y H:i:s'),
            default => (string) $value,
        };
    }

    /**
     * Format date value
     */
    public function formatDate(mixed $value, string $format = 'd-M-Y'): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        if (class_exists('\Carbon\Carbon')) {
            return \Carbon\Carbon::parse($value)->format($format);
        }

        return date($format, strtotime($value));
    }

    /**
     * Format datetime value
     */
    public function formatDateTime(mixed $value, string $format = 'd-M-Y H:i:s'): ?string
    {
        return $this->formatDate($value, $format);
    }

    /**
     * Get Excel number format string for a column type
     */
    public function getExcelFormat(string $type, string $locale = 'en_US'): string
    {
        $numberFormat = $this->getLocaleNumberFormat($locale);

        return match ($type) {
            ColumnTypes::AMOUNT, ColumnTypes::AMOUNT_PLAIN => $numberFormat,
            ColumnTypes::INTEGER => '#,##0',
            ColumnTypes::QUANTITY => $numberFormat,
            ColumnTypes::PERCENTAGE => '0.00%',
            ColumnTypes::DATE => 'DD-MMM-YYYY',
            ColumnTypes::DATETIME => 'DD-MMM-YYYY HH:MM:SS',
            default => 'General',
        };
    }

    /**
     * Get number format based on locale
     */
    protected function getLocaleNumberFormat(string $locale): string
    {
        $locales = config('exporter.locale.locales', []);

        if (isset($locales[$locale]['number_format'])) {
            return $locales[$locale]['number_format'];
        }

        // Default format
        return '#,##0.00';
    }

    /**
     * Get currency symbol for locale
     */
    public function getCurrencySymbol(string $locale): string
    {
        $locales = config('exporter.locale.locales', []);

        if (isset($locales[$locale]['currency_symbol'])) {
            return $locales[$locale]['currency_symbol'];
        }

        return $this->currencySymbol;
    }

    /**
     * Set locale
     */
    public function setLocale(string $locale): static
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * Set currency symbol
     */
    public function setCurrencySymbol(string $symbol): static
    {
        $this->currencySymbol = $symbol;
        return $this;
    }

    /**
     * Set decimal places
     */
    public function setDecimalPlaces(int $places): static
    {
        $this->decimalPlaces = $places;
        return $this;
    }
}
