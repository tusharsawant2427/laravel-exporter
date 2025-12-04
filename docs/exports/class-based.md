# Class-Based Exports

Class-based exports provide a clean, reusable way to define exports. This approach is similar to [Maatwebsite Excel](https://laravel-excel.com/) and is ideal for complex, reusable exports.

## Why Class-Based Exports?

| Fluent API | Class-Based |
|------------|-------------|
| Quick, inline exports | Reusable across application |
| Good for simple exports | Better for complex logic |
| Less boilerplate | More organized code |
| One-off exports | Testable exports |

## Creating an Export Class

### Basic Export (FromCollection)

```php
<?php

namespace App\Exports;

use App\Models\User;
use LaravelExporter\Concerns\FromCollection;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\Exportable;

class UsersExport implements FromCollection, WithHeadings
{
    use Exportable;

    public function collection()
    {
        return User::all();
    }

    public function headings(): array
    {
        return ['ID', 'Name', 'Email', 'Created At'];
    }
}
```

### Query-Based Export (FromQuery) - Recommended

More memory-efficient for large datasets:

```php
<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\Exportable;

class UsersExport implements FromQuery, WithHeadings
{
    use Exportable;

    public function query(): Builder
    {
        return User::query()->orderBy('name');
    }

    public function headings(): array
    {
        return ['ID', 'Name', 'Email', 'Created At'];
    }
}
```

### Array-Based Export (FromArray)

```php
<?php

namespace App\Exports;

use LaravelExporter\Concerns\FromArray;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\Exportable;

class StatisticsExport implements FromArray, WithHeadings
{
    use Exportable;

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return ['Metric', 'Value', 'Change'];
    }
}
```

### Generator-Based Export (FromGenerator)

For custom iteration logic:

```php
<?php

namespace App\Exports;

use Generator;
use LaravelExporter\Concerns\FromGenerator;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\Exportable;

class LogExport implements FromGenerator, WithHeadings
{
    use Exportable;

    public function generator(): Generator
    {
        $handle = fopen(storage_path('logs/laravel.log'), 'r');
        
        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*)/', $line, $matches)) {
                yield [
                    'timestamp' => $matches[1],
                    'channel' => $matches[2],
                    'level' => $matches[3],
                    'message' => substr($matches[4], 0, 100),
                ];
            }
        }
        
        fclose($handle);
    }

    public function headings(): array
    {
        return ['Timestamp', 'Channel', 'Level', 'Message'];
    }
}
```

## Using Export Classes

### Download

```php
use LaravelExporter\Facades\Excel;
use App\Exports\UsersExport;

// In a controller
return Excel::download(new UsersExport, 'users.xlsx');
```

### Store to Disk

```php
// Store to default disk (storage/app)
Excel::store(new UsersExport, 'exports/users.xlsx');

// Store to specific disk
Excel::store(new UsersExport, 'exports/users.xlsx', 's3');
```

### Using the Exportable Trait

The `Exportable` trait adds convenient methods:

```php
use App\Exports\UsersExport;

// Download directly from export class
return (new UsersExport)->download('users.xlsx');

// Store from export class
(new UsersExport)->store('exports/users.xlsx', 'local');
```

## Available Concerns

### Data Source Concerns

| Concern | Description |
|---------|-------------|
| `FromCollection` | Use a Laravel Collection |
| `FromQuery` | Use an Eloquent Builder (memory efficient) |
| `FromArray` | Use a PHP array |
| `FromGenerator` | Use a Generator |

### Header & Mapping Concerns

| Concern | Description |
|---------|-------------|
| `WithHeadings` | Add column headers |
| `WithMapping` | Transform each row |
| `WithColumnDefinitions` | Define column types |

### Formatting Concerns

| Concern | Description |
|---------|-------------|
| `WithColumnFormatting` | Apply number/date formats |
| `WithColumnWidths` | Set column widths |
| `WithStyles` | Apply cell styles |
| `ShouldAutoSize` | Auto-size columns |
| `WithConditionalColoring` | Color based on values |
| `WithConditionalFormatting` | Advanced conditional formatting |

### Layout Concerns

| Concern | Description |
|---------|-------------|
| `WithTitle` | Set sheet title |
| `WithFreezeRow` | Freeze header row |
| `WithAutoFilter` | Add filter dropdowns |
| `WithReportHeader` | Add report header block |
| `WithTotals` | Add totals row |

### Multi-Sheet Concerns

| Concern | Description |
|---------|-------------|
| `WithMultipleSheets` | Export multiple sheets |

### Processing Concerns

| Concern | Description |
|---------|-------------|
| `WithChunkReading` | Process in chunks |
| `WithEvents` | Hook into export lifecycle |

## Concern Examples

### WithMapping

Transform rows before export:

```php
<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\WithMapping;
use LaravelExporter\Concerns\Exportable;

class OrdersExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    public function query(): Builder
    {
        return Order::query()->with('customer');
    }

    public function headings(): array
    {
        return ['Order #', 'Customer', 'Status', 'Total', 'Date'];
    }

    public function map($order): array
    {
        return [
            $order->order_number,
            $order->customer->name,
            ucfirst($order->status),
            number_format($order->total, 2),
            $order->created_at->format('d-M-Y'),
        ];
    }
}
```

### WithStyles (PhpSpreadsheet)

Apply cell styles:

```php
<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\WithStyles;
use LaravelExporter\Concerns\Exportable;

class StyledExport implements FromQuery, WithHeadings, WithStyles
{
    use Exportable;

    public function query(): Builder
    {
        return Order::query();
    }

    public function headings(): array
    {
        return ['Order #', 'Amount', 'Status'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Style the first row (headers)
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => 'solid',
                    'color' => ['rgb' => '4472C4'],
                ],
            ],
            // Style column A
            'A' => ['font' => ['bold' => true]],
            // Style a specific cell
            'B2' => ['font' => ['italic' => true]],
        ];
    }
}
```

### WithColumnFormatting (PhpSpreadsheet)

Apply number formats:

```php
<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\WithColumnFormatting;
use LaravelExporter\Concerns\Exportable;

class FormattedExport implements FromQuery, WithHeadings, WithColumnFormatting
{
    use Exportable;

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_NUMBER,
            'B' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'C' => NumberFormat::FORMAT_PERCENTAGE_00,
            'D' => 'dd-mmm-yyyy',
        ];
    }
}
```

### WithTotals

Add a totals row:

```php
<?php

namespace App\Exports;

use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\WithTotals;
use LaravelExporter\Concerns\Exportable;

class SalesExport implements FromQuery, WithHeadings, WithTotals
{
    use Exportable;

    public function headings(): array
    {
        return ['Product', 'Quantity', 'Price', 'Total'];
    }

    public function totalColumns(): array
    {
        return ['Quantity', 'Price', 'Total'];  // Columns to sum
    }

    public function totalLabel(): string
    {
        return 'GRAND TOTAL';
    }
}
```

### WithReportHeader

Add a professional header:

```php
<?php

namespace App\Exports;

use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\WithReportHeader;
use LaravelExporter\Support\ReportHeader;
use LaravelExporter\Concerns\Exportable;

class ReportExport implements FromQuery, WithHeadings, WithReportHeader
{
    use Exportable;

    public function reportHeader(): ReportHeader
    {
        return ReportHeader::make()
            ->company('Acme Corporation')
            ->title('Monthly Sales Report')
            ->subtitle('All Regions')
            ->dateRange('01-Nov-2024', '30-Nov-2024')
            ->generatedBy(auth()->user()->name)
            ->generatedAt();
    }
}
```

### WithMultipleSheets

Export to multiple sheets:

```php
<?php

namespace App\Exports;

use LaravelExporter\Concerns\WithMultipleSheets;
use LaravelExporter\Concerns\Exportable;

class WorkbookExport implements WithMultipleSheets
{
    use Exportable;

    protected array $months;

    public function __construct(array $months)
    {
        $this->months = $months;
    }

    public function sheets(): array
    {
        $sheets = [];
        
        foreach ($this->months as $month) {
            $sheets[$month] = new MonthlySalesExport($month);
        }
        
        return $sheets;
    }
}
```

## Constructor Injection

Pass parameters to customize exports:

```php
<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\Exportable;

class FilteredOrdersExport implements FromQuery, WithHeadings
{
    use Exportable;

    protected string $status;
    protected string $fromDate;
    protected string $toDate;

    public function __construct(string $status, string $fromDate, string $toDate)
    {
        $this->status = $status;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }

    public function query(): Builder
    {
        return Order::query()
            ->where('status', $this->status)
            ->whereBetween('created_at', [$this->fromDate, $this->toDate]);
    }

    public function headings(): array
    {
        return ['Order #', 'Customer', 'Total', 'Date'];
    }
}

// Usage
return Excel::download(
    new FilteredOrdersExport('completed', '2024-01-01', '2024-12-31'),
    'completed-orders.xlsx'
);
```

## Full Example

```php
<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\WithMapping;
use LaravelExporter\Concerns\WithStyles;
use LaravelExporter\Concerns\WithColumnFormatting;
use LaravelExporter\Concerns\WithColumnWidths;
use LaravelExporter\Concerns\WithTotals;
use LaravelExporter\Concerns\WithReportHeader;
use LaravelExporter\Concerns\ShouldAutoSize;
use LaravelExporter\Concerns\WithFreezeRow;
use LaravelExporter\Concerns\WithAutoFilter;
use LaravelExporter\Concerns\Exportable;
use LaravelExporter\Support\ReportHeader;

class ComprehensiveOrdersExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnFormatting,
    WithColumnWidths,
    WithTotals,
    WithReportHeader,
    ShouldAutoSize,
    WithFreezeRow,
    WithAutoFilter
{
    use Exportable;

    protected string $fromDate;
    protected string $toDate;

    public function __construct(string $fromDate, string $toDate)
    {
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }

    public function query(): Builder
    {
        return Order::query()
            ->with('customer')
            ->whereBetween('created_at', [$this->fromDate, $this->toDate])
            ->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return ['Order #', 'Date', 'Customer', 'Status', 'Items', 'Total'];
    }

    public function map($order): array
    {
        return [
            $order->order_number,
            $order->created_at->format('d-M-Y'),
            $order->customer->name,
            ucfirst($order->status),
            $order->items_count,
            $order->total,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnFormats(): array
    {
        return [
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

    public function reportHeader(): ReportHeader
    {
        return ReportHeader::make()
            ->company(config('app.name'))
            ->title('Orders Report')
            ->dateRange($this->fromDate, $this->toDate)
            ->generatedBy(auth()->user()?->name ?? 'System')
            ->generatedAt();
    }

    public function freezeRow(): int
    {
        return 1;
    }
}
```

Usage:

```php
return Excel::download(
    new ComprehensiveOrdersExport('2024-01-01', '2024-12-31'),
    'orders-2024.xlsx'
);
```
