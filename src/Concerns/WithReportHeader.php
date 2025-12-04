<?php

namespace LaravelExporter\Concerns;

use LaravelExporter\Support\ReportHeader;

/**
 * Add a report header with title, subtitle, and additional info.
 */
interface WithReportHeader
{
    /**
     * @return ReportHeader
     */
    public function reportHeader(): ReportHeader;
}
