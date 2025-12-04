# Fluent API Exports

The Fluent API provides a clean, chainable interface for building exports quickly without creating separate classes.

## Basic Usage

```php
use LaravelExporter\Facades\Exporter;

Exporter::make()
    ->columns(['id', 'name', 'email'])
    ->from(User::query())
    ->download('users.csv');
```

## Builder Methods

### `make()`

Create a new export builder instance:

```php
$exporter = Exporter::make();
```

### `format(string $format)`

Set the export format. Supported: `csv`, `xlsx`, `json`

```php
Exporter::make()
    ->format('xlsx')
    ->from($data)
    ->download('export.xlsx');
```

Shortcut methods:
```php
Exporter::make()->asCsv()->from($data)->download('export.csv');
Exporter::make()->asExcel()->from($data)->download('export.xlsx');
Exporter::make()->asJson()->from($data)->download('export.json');
```

### `columns(array|callable $columns)`

Specify which columns to export:

```php
// Simple array
Exporter::make()
    ->columns(['id', 'name', 'email']);

// With column types (callable)
Exporter::make()
    ->columns(fn($cols) => $cols
        ->string('name', 'Name')
        ->amount('total', 'Total')
    );
```

### `headers(array $headers)`

Set custom header labels:

```php
Exporter::make()
    ->columns(['id', 'name', 'email'])
    ->headers(['User ID', 'Full Name', 'Email Address']);
```

### `from(object|array $source)`

Set the data source:

```php
// Eloquent Builder
Exporter::make()->from(User::query());
Exporter::make()->from(User::where('active', true));

// Collection
Exporter::make()->from(collect($data));

// Array
Exporter::make()->from($arrayData);

// LazyCollection (memory efficient)
Exporter::make()->from(User::lazy());
```

### `transformRow(Closure $callback)`

Transform each row before export:

```php
Exporter::make()
    ->transformRow(function (array $row, $originalItem) {
        $row['name'] = strtoupper($row['name']);
        $row['status'] = $originalItem->is_active ? 'Active' : 'Inactive';
        return $row;
    })
    ->from(User::query());
```

### `chunkSize(int $size)`

Set chunk size for processing large datasets:

```php
Exporter::make()
    ->chunkSize(500)
    ->from(User::query());
```

### `options(array $options)`

Set format-specific options:

```php
// CSV options
Exporter::make()
    ->format('csv')
    ->options([
        'delimiter' => ';',
        'enclosure' => "'",
        'add_bom' => true,
    ]);

// Excel options
Exporter::make()
    ->format('xlsx')
    ->options([
        'sheet_name' => 'Users',
        'freeze_header' => true,
        'auto_filter' => true,
    ]);

// JSON options
Exporter::make()
    ->format('json')
    ->options([
        'pretty_print' => true,
        'wrap_in_object' => true,
        'data_key' => 'users',
    ]);
```

### `locale(string $locale)`

Set locale for number formatting (Excel):

```php
Exporter::make()
    ->format('xlsx')
    ->locale('en_IN')  // Indian numbering
    ->from($data);
```

### `conditionalColoring(bool $enabled)`

Enable/disable conditional coloring for amounts:

```php
Exporter::make()
    ->format('xlsx')
    ->conditionalColoring(true)
    ->from($data);
```

## Output Methods

### `toFile(string $path)`

Save export to a file:

```php
Exporter::make()
    ->from($data)
    ->toFile(storage_path('app/exports/users.csv'));
```

### `download(string $filename)`

Return a download response:

```php
// In a controller
return Exporter::make()
    ->from($data)
    ->download('users.csv');
```

### `stream(string $filename)`

Stream the response (memory efficient for large files):

```php
return Exporter::make()
    ->from($data)
    ->stream('large-export.csv');
```

### `toString()`

Get export content as a string:

```php
$content = Exporter::make()
    ->from($data)
    ->toString();
```

## Column Definition API

When using callable columns, you get a fluent column builder:

```php
Exporter::make()
    ->columns(fn($cols) => $cols
        // String column
        ->string('name', 'Name')
        
        // Integer column
        ->integer('quantity', 'Qty')
        
        // Amount with conditional coloring
        ->amount('total', 'Total')
        
        // Amount without coloring
        ->amountPlain('price', 'Price')
        
        // Percentage
        ->percentage('discount', 'Discount %')
        
        // Date
        ->date('created_at', 'Created')
        
        // DateTime
        ->datetime('updated_at', 'Updated')
        
        // Boolean (Yes/No)
        ->boolean('is_active', 'Active')
        
        // Quantity
        ->quantity('items', 'Items')
    );
```

## Report Headers (Excel)

Add professional headers to Excel exports:

```php
Exporter::make()
    ->format('xlsx')
    ->header(fn($h) => $h
        ->company('Acme Corporation')
        ->title('Sales Report')
        ->subtitle('Q4 2024')
        ->dateRange('01-Oct-2024', '31-Dec-2024')
        ->generatedBy('John Doe')
        ->generatedAt()
    )
    ->from($data)
    ->download('report.xlsx');
```

## Totals Row (Excel)

Add automatic totals:

```php
Exporter::make()
    ->format('xlsx')
    ->columns(fn($cols) => $cols
        ->string('product', 'Product')
        ->quantity('qty', 'Quantity')
        ->amount('price', 'Price')
        ->amount('total', 'Total')
    )
    ->withTotals(['qty', 'price', 'total'])
    ->totalsLabel('GRAND TOTAL')
    ->from($data)
    ->download('products.xlsx');
```

## Complete Example

```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use LaravelExporter\Facades\Exporter;

class OrderExportController extends Controller
{
    public function export(Request $request)
    {
        $query = Order::query()
            ->with('customer')
            ->whereBetween('created_at', [
                $request->get('from', now()->startOfMonth()),
                $request->get('to', now()->endOfMonth()),
            ]);

        return Exporter::make()
            ->format($request->get('format', 'xlsx'))
            ->locale('en_IN')
            ->header(fn($h) => $h
                ->company(config('app.company_name'))
                ->title('Orders Report')
                ->dateRange(
                    $request->get('from', now()->startOfMonth()->format('d-M-Y')),
                    $request->get('to', now()->endOfMonth()->format('d-M-Y'))
                )
                ->generatedBy(auth()->user()->name)
                ->generatedAt()
            )
            ->columns(fn($cols) => $cols
                ->string('order_number', 'Order #')
                ->date('created_at', 'Date')
                ->string('customer.name', 'Customer')
                ->string('status', 'Status')
                ->quantity('items_count', 'Items')
                ->amount('subtotal', 'Subtotal')
                ->amount('discount', 'Discount')
                ->amount('tax', 'Tax')
                ->amount('total', 'Total')
            )
            ->transformRow(function ($row, $order) {
                $row['status'] = ucfirst($order->status);
                return $row;
            })
            ->withTotals(['subtotal', 'discount', 'tax', 'total'])
            ->totalsLabel('GRAND TOTAL')
            ->from($query)
            ->download('orders-report.xlsx');
    }
}
```

## Chaining All Together

```php
Exporter::make()
    // Format
    ->format('xlsx')
    ->locale('en_US')
    
    // Report header
    ->header(fn($h) => $h
        ->company('Company Name')
        ->title('Report Title')
    )
    
    // Columns
    ->columns(fn($cols) => $cols
        ->string('col1', 'Column 1')
        ->amount('col2', 'Column 2')
    )
    
    // Options
    ->options([
        'freeze_header' => true,
        'auto_filter' => true,
    ])
    
    // Transformations
    ->transformRow(fn($row) => $row)
    
    // Totals
    ->withTotals(['col2'])
    
    // Data source
    ->from(Model::query())
    
    // Output
    ->download('export.xlsx');
```
