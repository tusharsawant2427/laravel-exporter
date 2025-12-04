<?php

namespace LaravelExporter\Support;

/**
 * Cell Style Builder
 *
 * Provides a fluent interface for defining cell styles
 * that can be applied conditionally.
 */
class CellStyle
{
    protected ?string $backgroundColor = null;
    protected ?string $fontColor = null;
    protected bool $bold = false;
    protected bool $italic = false;
    protected bool $underline = false;
    protected ?string $alignment = null;
    protected ?int $fontSize = null;
    protected ?string $prefix = null;
    protected ?string $suffix = null;
    protected mixed $valueTransform = null;

    public static function make(): static
    {
        return new static();
    }

    /**
     * Set background color (hex without #, e.g., 'FF0000' for red)
     */
    public function background(string $color): static
    {
        $this->backgroundColor = ltrim($color, '#');
        return $this;
    }

    /**
     * Set font color (hex without #)
     */
    public function color(string $color): static
    {
        $this->fontColor = ltrim($color, '#');
        return $this;
    }

    /**
     * Shorthand for green color
     */
    public function green(): static
    {
        return $this->color('006400');
    }

    /**
     * Shorthand for red color
     */
    public function red(): static
    {
        return $this->color('DC0000');
    }

    /**
     * Shorthand for orange/warning color
     */
    public function orange(): static
    {
        return $this->color('FF8C00');
    }

    /**
     * Shorthand for blue color
     */
    public function blue(): static
    {
        return $this->color('0066CC');
    }

    /**
     * Shorthand for gray color
     */
    public function gray(): static
    {
        return $this->color('666666');
    }

    /**
     * Set bold text
     */
    public function bold(bool $bold = true): static
    {
        $this->bold = $bold;
        return $this;
    }

    /**
     * Set italic text
     */
    public function italic(bool $italic = true): static
    {
        $this->italic = $italic;
        return $this;
    }

    /**
     * Set underline text
     */
    public function underline(bool $underline = true): static
    {
        $this->underline = $underline;
        return $this;
    }

    /**
     * Set text alignment
     */
    public function align(string $alignment): static
    {
        $this->alignment = $alignment;
        return $this;
    }

    /**
     * Set font size
     */
    public function fontSize(int $size): static
    {
        $this->fontSize = $size;
        return $this;
    }

    /**
     * Add prefix to value
     */
    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * Add suffix to value
     */
    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;
        return $this;
    }

    /**
     * Transform the displayed value
     */
    public function value(callable|string $transform): static
    {
        $this->valueTransform = $transform;
        return $this;
    }

    /**
     * Highlight style (yellow background)
     */
    public function highlight(): static
    {
        return $this->background('FFFF00');
    }

    /**
     * Success style (green background, white text)
     */
    public function success(): static
    {
        return $this->background('28A745')->color('FFFFFF');
    }

    /**
     * Danger style (red background, white text)
     */
    public function danger(): static
    {
        return $this->background('DC3545')->color('FFFFFF');
    }

    /**
     * Warning style (orange background)
     */
    public function warning(): static
    {
        return $this->background('FFC107')->color('000000');
    }

    /**
     * Info style (blue background, white text)
     */
    public function info(): static
    {
        return $this->background('17A2B8')->color('FFFFFF');
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'background_color' => $this->backgroundColor,
            'font_color' => $this->fontColor,
            'bold' => $this->bold,
            'italic' => $this->italic,
            'underline' => $this->underline,
            'alignment' => $this->alignment,
            'font_size' => $this->fontSize,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'value_transform' => $this->valueTransform,
        ];
    }

    // Getters
    public function getBackgroundColor(): ?string { return $this->backgroundColor; }
    public function getFontColor(): ?string { return $this->fontColor; }
    public function isBold(): bool { return $this->bold; }
    public function isItalic(): bool { return $this->italic; }
    public function isUnderline(): bool { return $this->underline; }
    public function getAlignment(): ?string { return $this->alignment; }
    public function getFontSize(): ?int { return $this->fontSize; }
    public function getPrefix(): ?string { return $this->prefix; }
    public function getSuffix(): ?string { return $this->suffix; }
    public function getValueTransform(): mixed { return $this->valueTransform; }
}
