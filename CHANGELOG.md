# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Fixed fatal errors in `ExcelExporter` and `HybridExporter` when styling XLSX
  output with OpenSpout: these classes called immutable `with*()` style methods
  (`withFontBold()`, `withBackgroundColor()`, `withFontColor()`, `withFontSize()`,
  `withCellAlignment()`, `withCellVerticalAlignment()`, `withFontItalic()`,
  `withFontUnderline()`) that do not exist on OpenSpout 4.x/5.x's `Style` class,
  which only exposes mutable `set*()` setters. Any styled Excel export using
  OpenSpout (headers, conditional coloring, totals row, multi-sheet exports)
  would crash with `Call to undefined method`.
- `StyledOpenSpoutExporter` now honors the `bold_headers` option instead of
  always bolding header text regardless of configuration.
- Added `openspout/openspout` to `require-dev` and a regression test
  (`tests/Unit/OpenSpoutStyleCompatibilityTest.php`) covering the OpenSpout
  styling code paths in `ExcelExporter`, `HybridExporter`, and
  `StyledOpenSpoutExporter` to prevent this class of bug from recurring.

## [1.0.0] - 2024-12-02

### Added

- Initial release
- Fluent API for building exports
- CSV export with BOM support for Excel compatibility
- Excel export (XML format, native XLSX with OpenSpout)
- JSON export with optional metadata
- Memory-efficient processing using generators
- Eloquent query builder support with lazy loading
- Collection and array support
- Column selection with aliases
- Nested column access with dot notation
- Custom headers
- Row transformation callbacks
- Configurable chunk size for large datasets
- Laravel Facade support
- Exportable trait for models
- Publishable configuration
