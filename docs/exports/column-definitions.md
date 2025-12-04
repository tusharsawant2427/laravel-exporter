# Column Definitions

Column definitions allow you to specify data types, formatting, and behavior for each column in your export.

## Quick Reference

| Type | Method | Description | Format |
|------|--------|-------------|--------|
| String | `->string()` | Plain text | General |
| Integer | `->integer()` | Whole numbers | #,##0 |
| Amount | `->amount()` | Currency with colors | #,##0.00 |
| Amount Plain | `->amountPlain()` | Currency without colors | #,##0.00 |
| Percentage | `->percentage()` | Percentage values | 0.00% |
| Date | `->date()` | Date only | DD-MMM-YYYY |
| DateTime | `->datetime()` | Date and time | DD-MMM-YYYY HH:MM:SS |
| Boolean | `->boolean()` | Yes/No display | General |
| Quantity | `->quantity()` | Numeric quantities | #,##0.00 |

## Using Column Definitions

### Fluent API

```php
use LaravelExporter\Facades\Exporter;

Exporter::make()
    ->format('xlsx')
    ->columns(fn($cols) => $cols
        ->string('order_number', 'Order #')
        ->date('order_date', 'Date')
        ->string('customer_name', 'Customer')
        ->amount('total', 'Total Amount')
        ->quantity('items', 'Item Count')
    )
    ->from(Order::query())
    ->download('orders.xlsx');
```

### Alternative Syntax

```php
Exporter::make()
    ->defineColumns(function ($cols) {
        $cols->string('name', 'Name');
        $cols->amount('total', 'Total');
    })
    ->from($data)
    ->download('export.xlsx');
```

### Class-Based Exports

```php
<?php

namespace App\Exports;

use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithColumnDefinitions;
use LaravelExporter\Support\ColumnCollection;

class OrdersExport implements FromQuery, WithColumnDefinitions
{
    public function columnDefinitions(ColumnCollection $columns): void
    {
        $columns
            ->string('order_number', 'Order #')
            ->date('order_date', 'Date')
            ->amount('total', 'Amount');
    }
}
```

## Column Types in Detail

### String

Plain text values:

```php
$cols->string('name', 'Full Name');
$cols->string('email', 'Email Address');
$cols->string('address', 'Shipping Address');
```

### Integer

Whole numbers with thousand separators:

```php
$cols->integer('quantity', 'Qty');
$cols->integer('stock', 'In Stock');
$cols->integer('views', 'Page Views');
```

Formatted as: `1,234`

### Amount

Currency values with conditional coloring (green for positive, red for negative):

```php
$cols->amount('total', 'Order Total');
$cols->amount('balance', 'Account Balance');
$cols->amount('profit', 'Profit/Loss');
```

Formatted as: `1,234.56` (with colors)

### Amount Plain

Currency values without coloring:

```php
$cols->amountPlain('price', 'Unit Price');
$cols->amountPlain('cost', 'Cost');
```

Formatted as: `1,234.56` (no colors)

### Percentage

Percentage values:

```php
$cols->percentage('discount', 'Discount %');
$cols->percentage('tax_rate', 'Tax Rate');
$cols->percentage('growth', 'YoY Growth');
```

Formatted as: `12.50%`

### Date

Date-only values:

```php
$cols->date('order_date', 'Order Date');
$cols->date('due_date', 'Due Date');
```

Formatted as: `15-Nov-2024`

### DateTime

Date with time:

```php
$cols->datetime('created_at', 'Created');
$cols->datetime('updated_at', 'Last Updated');
```

Formatted as: `15-Nov-2024 14:30:00`

### Boolean

True/false as readable text:

```php
$cols->boolean('is_active', 'Active');
$cols->boolean('is_verified', 'Verified');
```

Displayed as: `Yes` / `No`

### Quantity

Numeric quantities:

```php
$cols->quantity('items', 'Items');
$cols->quantity('weight', 'Weight (kg)');
```

Formatted as: `1,234.00`

## Advanced Column Options

### Column Width

Set explicit column width:

```php
$cols->string('description', 'Description')->width(50);
$cols->string('id', 'ID')->width(10);
```

### Column Alignment

```php
$cols->string('id', 'ID')->align('center');
$cols->amount('total', 'Total')->align('right');
$cols->string('name', 'Name')->align('left');
```

### Decimal Places

```php
$cols->amount('price', 'Price')->decimals(4);  // 1,234.5678
$cols->quantity('weight', 'Weight')->decimals(3);  // 1.234
```

### Custom Number Format

```php
$cols->string('phone', 'Phone')->format('(###) ###-####');
$cols->amount('amount', 'Amount')->format('$#,##0.00');
```

### Hide Column

```php
$cols->string('internal_id', 'ID')->hidden();
```

### Transformers

Apply a transformer to column values:

```php
$cols->string('status', 'Status')->transform(fn($value) => ucfirst($value));
$cols->string('name', 'Name')->transform(fn($value) => strtoupper($value));
$cols->date('date', 'Date')->transform(fn($value) => Carbon::parse($value));
```

## Conditional Styles

Apply styles based on cell values:

```php
use LaravelExporter\Support\CellStyle;

$cols->amount('balance', 'Balance')
    ->when(
        fn($value) => $value < 0,
        CellStyle::make()->fontColor('FF0000')->bold()
    )
    ->when(
        fn($value) => $value > 10000,
        CellStyle::make()->fontColor('008000')->bold()
    );
```

## Combining with Report Headers & Totals

```php
Exporter::make()
    ->format('xlsx')
    ->header(fn($h) => $h
        ->company('Acme Corp')
        ->title('Sales Report')
    )
    ->columns(fn($cols) => $cols
        ->string('product', 'Product')
        ->quantity('qty', 'Quantity')
        ->amountPlain('price', 'Unit Price')
        ->amount('total', 'Line Total')
    )
    ->withTotals(['qty', 'total'])
    ->totalsLabel('GRAND TOTAL')
    ->from($data)
    ->download('sales.xlsx');
```

## Locale-Aware Formatting

Column formatting respects the export locale:

```php
// US Format: $1,234.56
Exporter::make()
    ->format('xlsx')
    ->locale('en_US')
    ->columns(fn($cols) => $cols->amount('total', 'Total'))
    ->from($data)
    ->download('us-report.xlsx');

// Indian Format: ₹12,34,567.00
Exporter::make()
    ->format('xlsx')
    ->locale('en_IN')
    ->columns(fn($cols) => $cols->amount('total', 'Total'))
    ->from($data)
    ->download('india-report.xlsx');

// German Format: 1.234,56 €
Exporter::make()
    ->format('xlsx')
    ->locale('de_DE')
    ->columns(fn($cols) => $cols->amount('total', 'Total'))
    ->from($data)
    ->download('german-report.xlsx');
```

## Column from Relationships

Access related model data using dot notation:

```php
$cols->string('customer.name', 'Customer');
$cols->string('customer.email', 'Customer Email');
$cols->string('category.name', 'Category');
$cols->amount('order.total', 'Order Total');
```

Make sure to eager load the relationships:

```php
Exporter::make()
    ->columns(fn($cols) => $cols
        ->string('name', 'Product')
        ->string('category.name', 'Category')
    )
    ->from(Product::with('category'))
    ->download('products.xlsx');
```

## Complete Example

```php
use LaravelExporter\Facades\Exporter;
use LaravelExporter\Support\CellStyle;

Exporter::make()
    ->format('xlsx')
    ->locale('en_US')
    ->conditionalColoring(true)
    ->header(fn($h) => $h
        ->company('TechCorp Inc.')
        ->title('Q4 2024 Sales Report')
        ->dateRange('01-Oct-2024', '31-Dec-2024')
        ->generatedBy(auth()->user()->name)
    )
    ->columns(fn($cols) => $cols
        // Basic columns
        ->string('invoice_number', 'Invoice #')->width(15)
        ->date('invoice_date', 'Date')->width(12)
        ->string('customer.name', 'Customer')->width(25)
        
        // Quantity columns
        ->quantity('quantity', 'Qty')->width(10)->align('center')
        
        // Amount columns
        ->amountPlain('unit_price', 'Unit Price')->width(12)
        ->amountPlain('subtotal', 'Subtotal')->width(12)
        ->amount('discount', 'Discount')->width(12)
        ->amount('tax', 'Tax')->width(12)
        ->amount('total', 'Total')->width(15)
            ->when(
                fn($value) => $value > 10000,
                CellStyle::make()->bold()->fontColor('008000')
            )
        
        // Status column with transform
        ->string('status', 'Status')
            ->width(12)
            ->transform(fn($v) => strtoupper($v))
    )
    ->withTotals(['quantity', 'subtotal', 'discount', 'tax', 'total'])
    ->totalsLabel('QUARTERLY TOTALS')
    ->from(Invoice::with('customer')->whereQuarter('invoice_date', 4))
    ->download('q4-sales-report.xlsx');
```
