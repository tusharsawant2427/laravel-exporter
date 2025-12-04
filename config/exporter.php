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
        'delimiter' => ',',
        'enclosure' => '"',
        'escape' => '\\',
        'include_headers' => true,
        'encoding' => 'UTF-8',
        'add_bom' => true, // Add BOM for Excel compatibility
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
        'include_headers' => true,
        'sheet_name' => 'Sheet1',
        'freeze_header' => true,
        'auto_filter' => true,
        'conditional_coloring' => true,
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
        'default' => 'en_US',
        'currency_symbol' => '$',
        'date_format' => 'd-M-Y',
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
    | Number Formats
    |--------------------------------------------------------------------------
    |
    | Configure number formatting patterns for Excel exports.
    | These use Excel number format codes.
    |
    */
    'number_formats' => [
        'amount' => '#,##0.00',             // Standard format (1,234,567.00)
        'amount_no_decimals' => '#,##0',    // No decimal places
        'integer' => '#,##0',
        'percentage' => '0.00%',
        'quantity' => '#,##0.00',
        'currency' => '"$"#,##0.00',        // With currency symbol (configurable)
    ],

    /*
    |--------------------------------------------------------------------------
    | Styling Defaults
    |--------------------------------------------------------------------------
    |
    | Configure default styling options for Excel exports.
    |
    */
    'styling' => [
        'header' => [
            'background_color' => '#2C3E50',
            'font_color' => '#FFFFFF',
            'bold' => true,
        ],
        'totals' => [
            'background_color' => '#E8E8E8',
            'bold' => true,
            'border_style' => 'double',
        ],
        'conditional_colors' => [
            'positive' => '#006400',  // Dark Green
            'negative' => '#8B0000',  // Dark Red
        ],
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
        'pretty_print' => false,
        'wrap_in_object' => false,
        'data_key' => 'data',
        'include_metadata' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | The default storage path for exports. This is relative to the storage/app
    | directory.
    |
    */
    'storage_path' => 'exports',
];
