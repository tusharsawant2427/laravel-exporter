<?php

namespace LaravelExporter\Concerns;

/**
 * Define conditional formatting rules for Excel exports (Hybrid Exporter)
 *
 * Supported format types:
 * - 'cellIs': Compare cell value (greaterThan, lessThan, equal, between, etc.)
 * - 'colorScale': Gradient coloring based on values (2 or 3 color scale)
 * - 'dataBar': In-cell bar charts
 * - 'iconSet': Traffic lights, arrows, flags, etc.
 * - 'expression': Custom formula-based formatting
 *
 * Example return format:
 * [
 *     [
 *         'type' => 'colorScale',
 *         'range' => 'E2:E{lastRow}',  // Use {lastRow}, {lastColumn} placeholders
 *         'minColor' => 'F8696B',       // Red for low values
 *         'maxColor' => '63BE7B',       // Green for high values
 *     ],
 *     [
 *         'type' => 'cellIs',
 *         'range' => 'F2:F{lastRow}',
 *         'operator' => 'equal',
 *         'value' => '"Completed"',
 *         'style' => ['fill' => '00FF00'],
 *     ],
 *     [
 *         'type' => 'dataBar',
 *         'range' => 'G2:G{lastRow}',
 *         'color' => '638EC6',          // Blue bars
 *     ],
 *     [
 *         'type' => 'iconSet',
 *         'range' => 'H2:H{lastRow}',
 *         'iconStyle' => '3TrafficLights1',
 *     ],
 * ]
 */
interface WithConditionalFormatting
{
    /**
     * Return an array of conditional formatting rules
     */
    public function conditionalFormats(): array;
}
