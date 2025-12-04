# Styling & Formatting

This guide covers all styling and formatting options available for Excel exports.

## Report Headers

Add professional headers to your reports:

### Fluent API

```php
use LaravelExporter\Facades\Exporter;

Exporter::make()
    ->format('xlsx')
    ->header(fn($h) => $h
        ->company('Acme Corporation')
        ->title('Sales Report')
        ->subtitle('Monthly Summary')
        ->dateRange('01-Nov-2024', '30-Nov-2024')
        ->generatedBy('John Doe')
        ->generatedAt()
    )
    ->from($data)
    ->download('report.xlsx');
```

### Class-Based Export

```php
<?php

namespace App\Exports;

use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithReportHeader;
use LaravelExporter\Support\ReportHeader;

class SalesExport implements FromQuery, WithReportHeader
{
    public function reportHeader(): ReportHeader
    {
        return ReportHeader::make()
            ->company('Acme Corporation')
            ->title('Sales Report')
            ->addLine('Region: North America')
            ->dateRange('01-Nov-2024', '30-Nov-2024')
            ->generatedBy(auth()->user()->name)
            ->generatedAt();
    }
}
```

### ReportHeader Methods

| Method | Description |
|--------|-------------|
| `company(string)` | Company/organization name |
| `title(string)` | Report title |
| `subtitle(string)` | Report subtitle |
| `dateRange(from, to)` | Date range display |
| `generatedBy(string)` | Who generated the report |
| `generatedAt()` | Auto timestamp |
| `addLine(string)` | Add custom line |

## Conditional Coloring

Automatically color amounts based on their values:

### Enable/Disable

```php
// Enable (default)
Exporter::make()
    ->format('xlsx')
    ->conditionalColoring(true)
    ->from($data);

// Disable
Exporter::make()
    ->format('xlsx')
    ->conditionalColoring(false)
    ->from($data);
```

### Amount Columns

When using amount columns with conditional coloring:

- **Positive values**: Green (#008000)
- **Negative values**: Red (#FF0000)
- **Zero values**: Black (#000000)

```php
$cols->amount('profit', 'Profit/Loss');  // Has conditional coloring
$cols->amountPlain('price', 'Price');    // No conditional coloring
```

## Column Widths

### Fluent API

```php
Exporter::make()
    ->format('xlsx')
    ->options([
        'column_widths' => [
            'A' => 15,
            'B' => 30,
            'C' => 12,
        ],
    ])
    ->from($data);
```

### Using Column Definitions

```php
$cols->string('id', 'ID')->width(10);
$cols->string('description', 'Description')->width(50);
```

### Class-Based Export

```php
use LaravelExporter\Concerns\WithColumnWidths;

class MyExport implements FromQuery, WithColumnWidths
{
    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 30,
            'C' => 12,
            'D' => 20,
        ];
    }
}
```

### Auto-Size Columns

```php
use LaravelExporter\Concerns\ShouldAutoSize;

class MyExport implements FromQuery, ShouldAutoSize
{
    // Columns will auto-size to fit content
}
```

## Cell Styles (PhpSpreadsheet)

For advanced styling, use the `WithStyles` concern:

```php
<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithStyles;

class StyledExport implements FromQuery, WithStyles
{
    public function styles(Worksheet $sheet): array
    {
        return [
            // Style row 1 (headers)
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
            
            // Style column A
            'A' => [
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                ],
            ],
            
            // Style a specific cell
            'D2' => [
                'font' => ['italic' => true],
            ],
            
            // Style a cell range
            'A1:F1' => [
                'borders' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_THICK,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ],
        ];
    }
}
```

## Number Formatting (PhpSpreadsheet)

```php
<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithColumnFormatting;

class FormattedExport implements FromQuery, WithColumnFormatting
{
    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_NUMBER,           // 1234
            'B' => NumberFormat::FORMAT_NUMBER_00,        // 1234.00
            'C' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,  // 1,234.00
            'D' => NumberFormat::FORMAT_PERCENTAGE_00,    // 12.34%
            'E' => 'dd-mmm-yyyy',                         // 15-Nov-2024
            'F' => 'dd/mm/yyyy hh:mm',                    // 15/11/2024 14:30
            'G' => '$#,##0.00',                           // $1,234.00
            'H' => '₹#,##0.00',                           // ₹1,234.00
        ];
    }
}
```

### Common Number Formats

| Format | Example | Code |
|--------|---------|------|
| Integer | 1,234 | `#,##0` |
| Decimal | 1,234.56 | `#,##0.00` |
| Percentage | 12.34% | `0.00%` |
| Currency (USD) | $1,234.56 | `$#,##0.00` |
| Currency (EUR) | €1,234.56 | `€#,##0.00` |
| Date | 15-Nov-2024 | `dd-mmm-yyyy` |
| Time | 14:30:00 | `hh:mm:ss` |
| DateTime | 15-Nov-2024 14:30 | `dd-mmm-yyyy hh:mm` |

## Freeze Panes

Keep header row visible when scrolling:

### Fluent API

```php
Exporter::make()
    ->format('xlsx')
    ->options(['freeze_header' => true])
    ->from($data);
```

### Class-Based Export

```php
use LaravelExporter\Concerns\WithFreezeRow;

class MyExport implements FromQuery, WithFreezeRow
{
    public function freezeRow(): int
    {
        return 1;  // Freeze first row
    }
}
```

## Auto Filter

Add filter dropdowns to headers:

### Fluent API

```php
Exporter::make()
    ->format('xlsx')
    ->options(['auto_filter' => true])
    ->from($data);
```

### Class-Based Export

```php
use LaravelExporter\Concerns\WithAutoFilter;

class MyExport implements FromQuery, WithAutoFilter
{
    // Auto-filter will be applied to headers
}
```

## Totals Row

Add a totals row at the bottom:

### Fluent API

```php
Exporter::make()
    ->format('xlsx')
    ->columns(fn($cols) => $cols
        ->string('product', 'Product')
        ->quantity('qty', 'Quantity')
        ->amount('total', 'Total')
    )
    ->withTotals(['qty', 'total'])
    ->totalsLabel('GRAND TOTAL')
    ->from($data);
```

### Class-Based Export

```php
use LaravelExporter\Concerns\WithTotals;

class MyExport implements FromQuery, WithTotals
{
    public function totalColumns(): array
    {
        return ['Quantity', 'Total'];  // Column headers to sum
    }

    public function totalLabel(): string
    {
        return 'GRAND TOTAL';
    }
}
```

## Conditional Formatting (PhpSpreadsheet)

Apply advanced conditional formatting:

```php
use LaravelExporter\Concerns\WithConditionalFormatting;

class MyExport implements FromQuery, WithConditionalFormatting
{
    public function conditionalFormatting(): array
    {
        return [
            // Highlight cells > 1000 in green
            'D' => [
                'type' => 'cellValue',
                'operator' => 'greaterThan',
                'value' => 1000,
                'style' => [
                    'fill' => ['color' => ['rgb' => 'C6EFCE']],
                    'font' => ['color' => ['rgb' => '006100']],
                ],
            ],
            // Highlight negative values in red
            'E' => [
                'type' => 'cellValue',
                'operator' => 'lessThan',
                'value' => 0,
                'style' => [
                    'fill' => ['color' => ['rgb' => 'FFC7CE']],
                    'font' => ['color' => ['rgb' => '9C0006']],
                ],
            ],
        ];
    }
}
```

## Sheet Title

Set the worksheet name:

### Fluent API

```php
Exporter::make()
    ->format('xlsx')
    ->options(['sheet_name' => 'Sales Data'])
    ->from($data);
```

### Class-Based Export

```php
use LaravelExporter\Concerns\WithTitle;

class MyExport implements FromQuery, WithTitle
{
    public function title(): string
    {
        return 'Sales Report Q4';
    }
}
```

## CellStyle Builder

For column definitions, use the CellStyle builder:

```php
use LaravelExporter\Support\CellStyle;

$cols->amount('balance', 'Balance')
    ->when(
        fn($value) => $value < 0,
        CellStyle::make()
            ->fontColor('FF0000')
            ->bold()
            ->backgroundColor('FFC7CE')
    )
    ->when(
        fn($value) => $value > 10000,
        CellStyle::make()
            ->fontColor('006100')
            ->bold()
            ->backgroundColor('C6EFCE')
    );
```

### CellStyle Methods

| Method | Description |
|--------|-------------|
| `fontColor(string)` | Font color (hex) |
| `backgroundColor(string)` | Cell background (hex) |
| `bold()` | Bold text |
| `italic()` | Italic text |
| `underline()` | Underlined text |
| `fontSize(int)` | Font size |
| `align(string)` | Horizontal alignment |
| `border(string)` | Border style |

## Complete Styling Example

```php
<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\WithMapping;
use LaravelExporter\Concerns\WithStyles;
use LaravelExporter\Concerns\WithColumnFormatting;
use LaravelExporter\Concerns\WithColumnWidths;
use LaravelExporter\Concerns\WithTotals;
use LaravelExporter\Concerns\WithReportHeader;
use LaravelExporter\Concerns\WithTitle;
use LaravelExporter\Concerns\WithFreezeRow;
use LaravelExporter\Concerns\WithAutoFilter;
use LaravelExporter\Concerns\Exportable;
use LaravelExporter\Support\ReportHeader;

class FullyStyledExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnFormatting,
    WithColumnWidths,
    WithTotals,
    WithReportHeader,
    WithTitle,
    WithFreezeRow,
    WithAutoFilter
{
    use Exportable;

    public function query(): Builder
    {
        return Order::query()->with('customer');
    }

    public function title(): string
    {
        return 'Orders Report';
    }

    public function reportHeader(): ReportHeader
    {
        return ReportHeader::make()
            ->company('Acme Corporation')
            ->title('Monthly Orders Report')
            ->subtitle('All Regions')
            ->dateRange('01-Nov-2024', '30-Nov-2024')
            ->generatedBy(auth()->user()->name ?? 'System')
            ->generatedAt();
    }

    public function headings(): array
    {
        return ['Order #', 'Date', 'Customer', 'Status', 'Items', 'Total'];
    }

    public function map($order): array
    {
        return [
            $order->order_number,
            $order->created_at,
            $order->customer->name,
            $order->status,
            $order->items_count,
            $order->total,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();
        
        return [
            // Header row styling
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 11,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            // Data cells
            "A2:F{$lastRow}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => 'dd-mmm-yyyy',
            'E' => NumberFormat::FORMAT_NUMBER,
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 12,
            'C' => 25,
            'D' => 12,
            'E' => 8,
            'F' => 15,
        ];
    }

    public function totalColumns(): array
    {
        return ['Items', 'Total'];
    }

    public function totalLabel(): string
    {
        return 'TOTALS';
    }

    public function freezeRow(): int
    {
        return 1;
    }
}
```
