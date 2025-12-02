<?php

namespace LaravelExporter\Traits;

use LaravelExporter\Styling\ExcelStyleBuilder;

/**
 * Has Styling Trait
 *
 * Provides functionality for applying styles to exports
 * including header styles, conditional formatting, etc.
 */
trait HasStyling
{
    /**
     * Style builder instance
     */
    protected ?ExcelStyleBuilder $styleBuilder = null;

    /**
     * Set style builder
     */
    public function styling(ExcelStyleBuilder|callable $style): static
    {
        if (is_callable($style)) {
            $this->styleBuilder = new ExcelStyleBuilder();
            $style($this->styleBuilder);
        } else {
            $this->styleBuilder = $style;
        }

        return $this;
    }

    /**
     * Set locale for number formatting
     */
    public function locale(string $locale): static
    {
        $this->ensureStyleBuilder();
        $this->styleBuilder->locale($locale);
        return $this;
    }

    /**
     * Enable/disable freeze header
     */
    public function freezeHeader(bool $freeze = true): static
    {
        $this->ensureStyleBuilder();
        $this->styleBuilder->freezeHeader($freeze);
        return $this;
    }

    /**
     * Enable/disable auto filter
     */
    public function autoFilter(bool $enable = true): static
    {
        $this->ensureStyleBuilder();
        $this->styleBuilder->autoFilter($enable);
        return $this;
    }

    /**
     * Enable/disable auto-size columns
     */
    public function autoSize(bool $enable = true): static
    {
        $this->ensureStyleBuilder();
        $this->styleBuilder->autoSize($enable);
        return $this;
    }

    /**
     * Enable alternate row coloring
     */
    public function alternateRows(bool $enable = true, ?string $color = null): static
    {
        $this->ensureStyleBuilder();
        $this->styleBuilder->alternateRows($enable, $color);
        return $this;
    }

    /**
     * Get style builder
     */
    public function getStyleBuilder(): ExcelStyleBuilder
    {
        $this->ensureStyleBuilder();
        return $this->styleBuilder;
    }

    /**
     * Check if styling is configured
     */
    public function hasStyling(): bool
    {
        return $this->styleBuilder !== null;
    }

    /**
     * Ensure style builder instance exists
     */
    protected function ensureStyleBuilder(): void
    {
        if ($this->styleBuilder === null) {
            $this->styleBuilder = new ExcelStyleBuilder();
        }
    }
}
