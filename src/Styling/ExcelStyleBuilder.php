<?php

namespace LaravelExporter\Styling;

use LaravelExporter\ColumnTypes;

/**
 * Excel Style Builder
 *
 * Provides fluent methods for building Excel cell styles
 * including conditional formatting for amounts.
 */
class ExcelStyleBuilder
{
    protected array $headerStyle = [];
    protected array $dataStyle = [];
    protected array $totalRowStyle = [];
    protected array $conditionalFormats = [];
    protected string $locale = 'en_IN';
    protected bool $freezeHeader = true;
    protected bool $autoFilter = true;
    protected bool $autoSize = true;

    public function __construct(array $config = [])
    {
        $this->locale = $config['locale'] ?? $this->locale;
        $this->initializeDefaultStyles();
    }

    /**
     * Initialize default styles
     */
    protected function initializeDefaultStyles(): void
    {
        $this->headerStyle = [
            'font' => [
                'bold' => true,
                'color' => '#FFFFFF',
            ],
            'background' => '#2C3E50',
            'alignment' => 'center',
            'border' => true,
        ];

        $this->dataStyle = [
            'border' => true,
            'alternateRows' => false,
        ];

        $this->totalRowStyle = [
            'font' => [
                'bold' => true,
            ],
            'background' => '#E8E8E8',
            'borderTop' => 'double',
            'borderBottom' => 'double',
        ];
    }

    /**
     * Set header style
     */
    public function headerStyle(array $style): static
    {
        $this->headerStyle = array_merge($this->headerStyle, $style);
        return $this;
    }

    /**
     * Set header background color
     */
    public function headerBackground(string $color): static
    {
        $this->headerStyle['background'] = $color;
        return $this;
    }

    /**
     * Set header font color
     */
    public function headerFontColor(string $color): static
    {
        $this->headerStyle['font']['color'] = $color;
        return $this;
    }

    /**
     * Enable/disable freeze header row
     */
    public function freezeHeader(bool $freeze = true): static
    {
        $this->freezeHeader = $freeze;
        return $this;
    }

    /**
     * Enable/disable auto filter
     */
    public function autoFilter(bool $enable = true): static
    {
        $this->autoFilter = $enable;
        return $this;
    }

    /**
     * Enable/disable auto-size columns
     */
    public function autoSize(bool $enable = true): static
    {
        $this->autoSize = $enable;
        return $this;
    }

    /**
     * Enable alternate row coloring
     */
    public function alternateRows(bool $enable = true, ?string $color = null): static
    {
        $this->dataStyle['alternateRows'] = $enable;
        if ($color) {
            $this->dataStyle['alternateRowColor'] = $color;
        }
        return $this;
    }

    /**
     * Set total row style
     */
    public function totalRowStyle(array $style): static
    {
        $this->totalRowStyle = array_merge($this->totalRowStyle, $style);
        return $this;
    }

    /**
     * Get conditional formatting rules for amount columns
     */
    public function getConditionalAmountFormat(): array
    {
        return [
            'positive' => [
                'type' => 'cellIs',
                'operator' => 'greaterThan',
                'value' => 0,
                'style' => [
                    'font' => ['color' => '#006400'], // Dark Green
                ],
            ],
            'negative' => [
                'type' => 'cellIs',
                'operator' => 'lessThan',
                'value' => 0,
                'style' => [
                    'font' => ['color' => '#8B0000'], // Dark Red
                ],
            ],
        ];
    }

    /**
     * Get number format for a column type
     */
    public function getNumberFormat(string $type): string
    {
        return match ($type) {
            ColumnTypes::AMOUNT, ColumnTypes::AMOUNT_PLAIN => $this->locale === 'en_IN'
                ? '#,##,##0.00'
                : '#,##0.00',
            ColumnTypes::INTEGER => '#,##0',
            ColumnTypes::QUANTITY => $this->locale === 'en_IN'
                ? '#,##,##0.00'
                : '#,##0.00',
            ColumnTypes::PERCENTAGE => '0.00%',
            ColumnTypes::DATE => 'DD-MMM-YYYY',
            ColumnTypes::DATETIME => 'DD-MMM-YYYY HH:MM:SS',
            default => 'General',
        };
    }

    /**
     * Get header style array
     */
    public function getHeaderStyle(): array
    {
        return $this->headerStyle;
    }

    /**
     * Get data style array
     */
    public function getDataStyle(): array
    {
        return $this->dataStyle;
    }

    /**
     * Get total row style array
     */
    public function getTotalRowStyle(): array
    {
        return $this->totalRowStyle;
    }

    /**
     * Check if header should be frozen
     */
    public function shouldFreezeHeader(): bool
    {
        return $this->freezeHeader;
    }

    /**
     * Check if auto filter should be enabled
     */
    public function shouldAutoFilter(): bool
    {
        return $this->autoFilter;
    }

    /**
     * Check if auto-size columns should be enabled
     */
    public function shouldAutoSize(): bool
    {
        return $this->autoSize;
    }

    /**
     * Set locale
     */
    public function locale(string $locale): static
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'header_style' => $this->headerStyle,
            'data_style' => $this->dataStyle,
            'total_row_style' => $this->totalRowStyle,
            'freeze_header' => $this->freezeHeader,
            'auto_filter' => $this->autoFilter,
            'locale' => $this->locale,
        ];
    }
}
