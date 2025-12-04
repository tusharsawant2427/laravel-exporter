# Configuration Reference

Complete reference for all configuration options in Laravel Exporter.

## Configuration File

After publishing the configuration:

```bash
php artisan vendor:publish --tag=exporter-config
```

You'll have `config/exporter.php` with all options.

## Full Configuration

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Export Format
    |--------------------------------------------------------------------------
    |
    | This option controls the default export format when none is specified.
    | Supported: "csv", "xlsx", "json"
    |
    */
    'default_format' => 'csv',

    /*
    |--------------------------------------------------------------------------
    | Chunk Size
    |--------------------------------------------------------------------------
    |
    | When exporting large datasets, data is processed in chunks to prevent
    | memory issues. This option controls the size of each chunk.
    |
    */
    'chunk_size' => 1000,

    /*
    |--------------------------------------------------------------------------
    | CSV Options
    |--------------------------------------------------------------------------
    |
    | Configure default options for CSV exports.
    |
    */
    'csv' => [
        // Field delimiter character
        'delimiter' => ',',
        
        // Field enclosure character
        'enclosure' => '"',
        
        // Escape character
        'escape' => '\\',
        
        // Include column headers as first row
        'include_headers' => true,
        
        // Output encoding
        'encoding' => 'UTF-8',
        
        // Add UTF-8 BOM for Excel compatibility
        // When true, Excel will properly display UTF-8 characters
        'add_bom' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Excel Options
    |--------------------------------------------------------------------------
    |
    | Configure default options for Excel exports.
    |
    */
    'excel' => [
        // Include column headers as first row
        'include_headers' => true,
        
        // Default sheet name
        'sheet_name' => 'Sheet1',
        
        // Freeze the header row
        'freeze_header' => true,
        
        // Enable auto-filter dropdown on headers
        'auto_filter' => true,
        
        // Enable conditional coloring for amount columns
        // Green for positive, red for negative values
        'conditional_coloring' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON Options
    |--------------------------------------------------------------------------
    |
    | Configure default options for JSON exports.
    |
    */
    'json' => [
        // Pretty print the JSON output
        'pretty_print' => false,
        
        // Wrap data in an object instead of array
        'wrap_in_object' => false,
        
        // Key name when wrap_in_object is true
        'data_key' => 'data',
        
        // Include metadata (count, generated_at, etc.)
        'include_metadata' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Locale Settings
    |--------------------------------------------------------------------------
    |
    | Configure locale-specific formatting options.
    |
    */
    'locale' => [
        // Default locale
        'default' => 'en_US',
        
        // Default currency symbol
        'currency_symbol' => '$',
        
        // Default date format
        'date_format' => 'd-M-Y',
        
        // Default datetime format
        'datetime_format' => 'd-M-Y H:i:s',

        /*
        |--------------------------------------------------------------------------
        | Supported Locales Configuration
        |--------------------------------------------------------------------------
        |
        | Define locale-specific settings for different countries.
        | You can add more locales as needed.
        |
        */
        'locales' => [
            'en_US' => [
                'currency_symbol' => '$',
                'number_format' => '#,##0.00',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
            'en_GB' => [
                'currency_symbol' => '£',
                'number_format' => '#,##0.00',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
            'en_IN' => [
                'currency_symbol' => '₹',
                'number_format' => '#,##,##0.00',  // Indian numbering
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
            'de_DE' => [
                'currency_symbol' => '€',
                'number_format' => '#.##0,00',
                'thousand_separator' => '.',
                'decimal_separator' => ',',
            ],
            'fr_FR' => [
                'currency_symbol' => '€',
                'number_format' => '# ##0,00',
                'thousand_separator' => ' ',
                'decimal_separator' => ',',
            ],
            'ja_JP' => [
                'currency_symbol' => '¥',
                'number_format' => '#,##0',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
            'zh_CN' => [
                'currency_symbol' => '¥',
                'number_format' => '#,##0.00',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Conditional Coloring
    |--------------------------------------------------------------------------
    |
    | Configure colors for conditional formatting on amount columns.
    |
    */
    'conditional_colors' => [
        'positive' => '008000',  // Green for positive amounts
        'negative' => 'FF0000',  // Red for negative amounts
        'zero' => '000000',      // Black for zero
    ],

    /*
    |--------------------------------------------------------------------------
    | Excel Styling
    |--------------------------------------------------------------------------
    |
    | Default styling options for Excel exports.
    |
    */
    'styling' => [
        'header' => [
            'background' => '4472C4',  // Blue header background
            'font_color' => 'FFFFFF',  // White font
            'bold' => true,
        ],
        'totals' => [
            'background' => 'E2EFDA',  // Light green
            'bold' => true,
        ],
    ],
];
```

## Runtime Configuration

You can override configuration at runtime:

### Format-Specific Options

```php
Exporter::make()
    ->format('csv')
    ->options([
        'delimiter' => ';',
        'enclosure' => "'",
        'add_bom' => false,
    ])
    ->from($data)
    ->download('export.csv');
```

### Locale Configuration

```php
Exporter::make()
    ->format('xlsx')
    ->locale('de_DE')  // German formatting
    ->from($data)
    ->download('export.xlsx');
```

### Chunk Size

```php
Exporter::make()
    ->chunkSize(500)  // Override default 1000
    ->from($data)
    ->toFile('export.csv');
```

### Conditional Coloring

```php
Exporter::make()
    ->format('xlsx')
    ->conditionalColoring(false)  // Disable coloring
    ->from($data)
    ->download('export.xlsx');
```

## Environment Variables

You can use environment variables for some settings:

```env
# .env
EXPORT_DEFAULT_FORMAT=xlsx
EXPORT_CHUNK_SIZE=500
EXPORT_LOCALE=en_GB
```

```php
// config/exporter.php
return [
    'default_format' => env('EXPORT_DEFAULT_FORMAT', 'csv'),
    'chunk_size' => env('EXPORT_CHUNK_SIZE', 1000),
    'locale' => [
        'default' => env('EXPORT_LOCALE', 'en_US'),
    ],
];
```

## Adding Custom Locales

To add a new locale, update the configuration:

```php
'locales' => [
    // ... existing locales
    
    'pt_BR' => [
        'currency_symbol' => 'R$',
        'number_format' => '#.##0,00',
        'thousand_separator' => '.',
        'decimal_separator' => ',',
    ],
    
    'ko_KR' => [
        'currency_symbol' => '₩',
        'number_format' => '#,##0',
        'thousand_separator' => ',',
        'decimal_separator' => '.',
    ],
],
```

## Per-Export Configuration

Different exports can have different configurations:

```php
// Sales report - Indian format
Exporter::make()
    ->format('xlsx')
    ->locale('en_IN')
    ->from(Sale::query())
    ->download('sales-india.xlsx');

// Same data - US format
Exporter::make()
    ->format('xlsx')
    ->locale('en_US')
    ->from(Sale::query())
    ->download('sales-us.xlsx');
```

## Configuration Best Practices

### 1. Use Environment-Specific Settings

```php
// config/exporter.php
return [
    'chunk_size' => app()->environment('production') ? 2000 : 500,
];
```

### 2. Separate Large File Settings

```php
// For normal exports
$normalExporter = Exporter::make()->chunkSize(1000);

// For large exports
$largeExporter = Exporter::make()->chunkSize(500);
```

### 3. Team Consistency

Keep locale settings consistent across the team:

```php
// config/exporter.php
'locale' => [
    'default' => 'en_IN',  // Company locale
    'currency_symbol' => '₹',
    'date_format' => 'd-M-Y',  // Standard date format
],
```
