# Installation Guide

This guide covers how to install and configure Laravel Exporter for production use.

## Requirements

- **PHP:** 8.1 or higher
- **Laravel:** 10.x, 11.x, or 12.x
- **Extensions:** `mbstring`, `xml` (for Excel features)

## Installation via Composer

### Basic Installation

```bash
composer require datasuite/laravel-exporter
```

The package uses Laravel's auto-discovery feature, so the service provider will be registered automatically.

### Optional Dependencies

For enhanced functionality, you can install these optional packages:

#### OpenSpout (Recommended for Excel)

For native `.xlsx` file support with streaming capabilities:

```bash
composer require openspout/openspout
```

**Benefits:**
- Native XLSX format (not XML-based)
- Memory-efficient streaming for large files
- Better compatibility with all Excel versions

#### PhpSpreadsheet (For Advanced Excel Features)

For advanced Excel features like formulas, conditional formatting, and cell merging:

```bash
composer require phpoffice/phpspreadsheet
```

**Benefits:**
- Excel formulas (SUM, AVERAGE, etc.)
- Dynamic conditional formatting
- Cell merging
- True auto-column sizing
- Chart support

## Configuration

### Publish Configuration File

To customize the default settings, publish the configuration file:

```bash
php artisan vendor:publish --tag=exporter-config
```

This creates `config/exporter.php` with all configurable options.

### Configuration Options

```php
// config/exporter.php
return [
    // Default export format: 'csv', 'xlsx', or 'json'
    'default_format' => 'csv',

    // Chunk size for large dataset processing
    'chunk_size' => 1000,

    // CSV-specific options
    'csv' => [
        'delimiter' => ',',
        'enclosure' => '"',
        'escape' => '\\',
        'include_headers' => true,
        'encoding' => 'UTF-8',
        'add_bom' => true, // Excel UTF-8 compatibility
    ],

    // Excel-specific options
    'excel' => [
        'include_headers' => true,
        'sheet_name' => 'Sheet1',
        'freeze_header' => true,
        'auto_filter' => true,
        'conditional_coloring' => true,
    ],

    // JSON-specific options
    'json' => [
        'pretty_print' => false,
        'wrap_in_object' => false,
        'data_key' => 'data',
        'include_metadata' => false,
    ],

    // Locale settings for number/currency formatting
    'locale' => [
        'default' => 'en_US',
        'currency_symbol' => '$',
        'date_format' => 'd-M-Y',
        'datetime_format' => 'd-M-Y H:i:s',
    ],
];
```

## Verify Installation

To verify the installation is working:

```php
use LaravelExporter\Facades\Exporter;

// Create a simple test export
$testData = [
    ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com'],
];

Exporter::make()
    ->from($testData)
    ->toFile(storage_path('app/test-export.csv'));

// Check if file was created
if (file_exists(storage_path('app/test-export.csv'))) {
    echo "Installation successful!";
}
```

## Service Provider Registration (Manual)

If auto-discovery is disabled, manually register the service provider:

```php
// config/app.php
'providers' => [
    // ...
    LaravelExporter\ExporterServiceProvider::class,
],

'aliases' => [
    // ...
    'Exporter' => LaravelExporter\Facades\Exporter::class,
    'Excel' => LaravelExporter\Facades\Excel::class,
],
```

## Directory Structure

After installation, the package structure is:

```
vendor/datasuite/laravel-exporter/
├── config/
│   └── exporter.php          # Configuration file
├── src/
│   ├── Concerns/             # Interface traits
│   ├── Contracts/            # Interfaces
│   ├── Facades/              # Laravel facades
│   ├── Formats/              # Format exporters
│   ├── Imports/              # Import helpers
│   ├── Readers/              # File readers
│   ├── Styling/              # Excel styling
│   ├── Support/              # Helper classes
│   ├── Traits/               # Reusable traits
│   ├── DataExporter.php      # Data export handler
│   ├── Excel.php             # Maatwebsite-style manager
│   ├── Exporter.php          # Fluent API builder
│   └── Importer.php          # Import handler
└── docs/                     # Documentation
```

## Troubleshooting

### Common Issues

#### 1. "Class not found" Error

Ensure you've run:
```bash
composer dump-autoload
```

#### 2. Excel Files Not Opening

If Excel files don't open correctly:
- For CSV: Ensure `add_bom => true` in config
- For XLSX: Install `openspout/openspout`

#### 3. Memory Issues with Large Exports

For large datasets (50K+ rows):
```php
// Use chunked processing
Exporter::make()
    ->chunkSize(500)
    ->from(Model::query())
    ->toFile('large-export.csv');
```

#### 4. Styling Not Working

Advanced Excel styling requires PhpSpreadsheet:
```bash
composer require phpoffice/phpspreadsheet
```

## Next Steps

- [Quick Start Guide](./quick-start.md) - Get started with basic exports
- [Configuration Reference](./configuration.md) - Detailed configuration options
- [Fluent API Guide](./exports/fluent-api.md) - Learn the fluent API
