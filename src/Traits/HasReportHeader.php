<?php

namespace LaravelExporter\Traits;

use LaravelExporter\Support\ReportHeader;

/**
 * Has Report Header Trait
 *
 * Provides functionality for adding report headers
 * including company name, title, date range, etc.
 */
trait HasReportHeader
{
    /**
     * Report header configuration
     */
    protected ?ReportHeader $reportHeader = null;

    /**
     * Set report header
     */
    public function header(ReportHeader|callable $header): static
    {
        if (is_callable($header)) {
            $this->reportHeader = ReportHeader::make();
            $header($this->reportHeader);
        } else {
            $this->reportHeader = $header;
        }

        return $this;
    }

    /**
     * Alias for header() - more descriptive name
     */
    public function withHeader(ReportHeader|callable $header): static
    {
        return $this->header($header);
    }

    /**
     * Set report title (shortcut)
     */
    public function title(string $title): static
    {
        $this->ensureHeader();
        $this->reportHeader->title($title);
        return $this;
    }

    /**
     * Set report subtitle (shortcut)
     */
    public function subtitle(string $subtitle): static
    {
        $this->ensureHeader();
        $this->reportHeader->subtitle($subtitle);
        return $this;
    }

    /**
     * Set company name (shortcut)
     */
    public function company(string $name): static
    {
        $this->ensureHeader();
        $this->reportHeader->company($name);
        return $this;
    }

    /**
     * Set date range (shortcut)
     */
    public function dateRange(string $from, string $to): static
    {
        $this->ensureHeader();
        $this->reportHeader->dateRange($from, $to);
        return $this;
    }

    /**
     * Check if header is set
     */
    public function hasHeader(): bool
    {
        return $this->reportHeader !== null && !$this->reportHeader->isEmpty();
    }

    /**
     * Get report header
     */
    public function getHeader(): ?ReportHeader
    {
        return $this->reportHeader;
    }

    /**
     * Get header rows count
     */
    public function getHeaderRowCount(): int
    {
        return $this->reportHeader?->getRowCount() ?? 0;
    }

    /**
     * Ensure header instance exists
     */
    protected function ensureHeader(): void
    {
        if ($this->reportHeader === null) {
            $this->reportHeader = ReportHeader::make();
        }
    }
}
