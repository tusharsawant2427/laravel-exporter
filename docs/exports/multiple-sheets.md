# Multiple Sheets Export

Export data to multiple worksheets in a single Excel file.

## Basic Multiple Sheets

### Class-Based Export

```php
<?php

namespace App\Exports;

use LaravelExporter\Concerns\WithMultipleSheets;
use LaravelExporter\Concerns\Exportable;

class WorkbookExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            'Products' => new ProductsExport(),
            'Orders' => new OrdersExport(),
            'Customers' => new CustomersExport(),
        ];
    }
}
```

Each sheet is a separate export class implementing standard concerns:

```php
<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\Exportable;

class ProductsExport implements FromQuery, WithHeadings
{
    use Exportable;

    public function query(): Builder
    {
        return Product::query();
    }

    public function headings(): array
    {
        return ['ID', 'SKU', 'Name', 'Price', 'Stock'];
    }
}
```

### Usage

```php
use LaravelExporter\Facades\Excel;
use App\Exports\WorkbookExport;

return Excel::download(new WorkbookExport, 'workbook.xlsx');
```

## Dynamic Sheets

Create sheets based on data:

### By Month

```php
<?php

namespace App\Exports;

use Carbon\Carbon;
use LaravelExporter\Concerns\WithMultipleSheets;
use LaravelExporter\Concerns\Exportable;

class YearlySalesExport implements WithMultipleSheets
{
    use Exportable;

    protected int $year;

    public function __construct(int $year)
    {
        $this->year = $year;
    }

    public function sheets(): array
    {
        $sheets = [];
        
        for ($month = 1; $month <= 12; $month++) {
            $monthName = Carbon::create($this->year, $month)->format('F');
            $sheets[$monthName] = new MonthlySalesExport($this->year, $month);
        }
        
        return $sheets;
    }
}
```

### By Category

```php
<?php

namespace App\Exports;

use App\Models\Category;
use LaravelExporter\Concerns\WithMultipleSheets;
use LaravelExporter\Concerns\Exportable;

class ProductsByCategoryExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return Category::all()
            ->mapWithKeys(fn($category) => [
                $category->name => new CategoryProductsExport($category->id)
            ])
            ->toArray();
    }
}
```

### By Region

```php
<?php

namespace App\Exports;

use LaravelExporter\Concerns\WithMultipleSheets;
use LaravelExporter\Concerns\Exportable;

class RegionalSalesExport implements WithMultipleSheets
{
    use Exportable;

    protected array $regions = ['North', 'South', 'East', 'West'];

    public function sheets(): array
    {
        $sheets = [];
        
        foreach ($this->regions as $region) {
            $sheets[$region] = new RegionExport($region);
        }
        
        return $sheets;
    }
}
```

## Sheet with Styling

Each sheet can have its own styling:

```php
<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\WithTitle;
use LaravelExporter\Concerns\WithStyles;
use LaravelExporter\Concerns\WithReportHeader;
use LaravelExporter\Support\ReportHeader;
use LaravelExporter\Concerns\Exportable;

class OrdersSheetExport implements FromQuery, WithHeadings, WithTitle, WithStyles, WithReportHeader
{
    use Exportable;

    protected string $status;

    public function __construct(string $status)
    {
        $this->status = $status;
    }

    public function query(): Builder
    {
        return Order::query()->where('status', $this->status);
    }

    public function title(): string
    {
        return ucfirst($this->status) . ' Orders';
    }

    public function headings(): array
    {
        return ['Order #', 'Customer', 'Total', 'Date'];
    }

    public function reportHeader(): ReportHeader
    {
        return ReportHeader::make()
            ->title(ucfirst($this->status) . ' Orders Report')
            ->generatedAt();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
```

Use in multi-sheet export:

```php
class OrdersByStatusExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            'Pending' => new OrdersSheetExport('pending'),
            'Completed' => new OrdersSheetExport('completed'),
            'Cancelled' => new OrdersSheetExport('cancelled'),
        ];
    }
}
```

## Fluent API Multiple Sheets

### Using Support\Sheet

```php
use LaravelExporter\Facades\Exporter;
use LaravelExporter\Support\Sheet;

Exporter::make()
    ->format('xlsx')
    ->sheets([
        Sheet::make('Products')
            ->columns(['id', 'name', 'price'])
            ->from(Product::query()),
        Sheet::make('Orders')
            ->columns(['id', 'total', 'date'])
            ->from(Order::query()),
    ])
    ->download('multi-sheet.xlsx');
```

### Sheet Builder

```php
Exporter::make()
    ->format('xlsx')
    ->addSheet('Summary', function ($sheet) {
        $sheet
            ->columns(['metric', 'value'])
            ->from([
                ['Total Sales', '$45,000'],
                ['Orders', '150'],
                ['Customers', '89'],
            ]);
    })
    ->addSheet('Details', function ($sheet) {
        $sheet
            ->columns(fn($cols) => $cols
                ->string('order_id', 'Order')
                ->amount('total', 'Total')
            )
            ->from(Order::query());
    })
    ->download('report.xlsx');
```

## Summary Sheet + Data Sheets

Common pattern: first sheet with summary, followed by detailed data:

```php
<?php

namespace App\Exports;

use LaravelExporter\Concerns\WithMultipleSheets;
use LaravelExporter\Concerns\Exportable;

class FullReportExport implements WithMultipleSheets
{
    use Exportable;

    protected string $fromDate;
    protected string $toDate;

    public function __construct(string $fromDate, string $toDate)
    {
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }

    public function sheets(): array
    {
        return [
            'Summary' => new SummaryExport($this->fromDate, $this->toDate),
            'Orders' => new OrdersExport($this->fromDate, $this->toDate),
            'Products' => new ProductSalesExport($this->fromDate, $this->toDate),
            'Customers' => new CustomerExport($this->fromDate, $this->toDate),
        ];
    }
}

class SummaryExport implements FromArray, WithHeadings
{
    protected string $fromDate;
    protected string $toDate;

    public function __construct(string $fromDate, string $toDate)
    {
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }

    public function array(): array
    {
        $orders = Order::whereBetween('created_at', [$this->fromDate, $this->toDate]);
        
        return [
            ['Total Orders', $orders->count()],
            ['Total Revenue', '$' . number_format($orders->sum('total'), 2)],
            ['Average Order', '$' . number_format($orders->avg('total'), 2)],
            ['Unique Customers', $orders->distinct('customer_id')->count()],
        ];
    }

    public function headings(): array
    {
        return ['Metric', 'Value'];
    }
}
```

## Passing Data Between Sheets

Share calculated data between sheets:

```php
<?php

namespace App\Exports;

use LaravelExporter\Concerns\WithMultipleSheets;
use LaravelExporter\Concerns\Exportable;

class DataSharingExport implements WithMultipleSheets
{
    use Exportable;

    protected array $summary = [];

    public function sheets(): array
    {
        // Calculate summary first
        $this->calculateSummary();
        
        return [
            'Summary' => new SummarySheet($this->summary),
            'Details' => new DetailsSheet(),
        ];
    }

    protected function calculateSummary(): void
    {
        $this->summary = [
            'total_orders' => Order::count(),
            'total_revenue' => Order::sum('total'),
            'avg_order_value' => Order::avg('total'),
        ];
    }
}
```

## Sheet Index vs Sheet Name

You can use either numeric indexes or names:

```php
public function sheets(): array
{
    return [
        // By name (recommended)
        'Sales' => new SalesExport(),
        'Inventory' => new InventoryExport(),
        
        // By index
        0 => new SalesExport(),
        1 => new InventoryExport(),
    ];
}
```

## Complete Example

```php
<?php

namespace App\Exports;

use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;
use Carbon\Carbon;
use LaravelExporter\Concerns\WithMultipleSheets;
use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\FromArray;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\WithMapping;
use LaravelExporter\Concerns\WithTitle;
use LaravelExporter\Concerns\WithReportHeader;
use LaravelExporter\Concerns\Exportable;
use LaravelExporter\Support\ReportHeader;

class ComprehensiveReportExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            'Dashboard' => new DashboardSheet(),
            'Orders' => new OrdersDetailSheet(),
            'Top Products' => new TopProductsSheet(),
            'Customer Analysis' => new CustomerAnalysisSheet(),
        ];
    }
}

class DashboardSheet implements FromArray, WithHeadings, WithTitle, WithReportHeader
{
    use Exportable;

    public function title(): string
    {
        return 'Dashboard';
    }

    public function reportHeader(): ReportHeader
    {
        return ReportHeader::make()
            ->company(config('app.name'))
            ->title('Business Dashboard')
            ->generatedAt();
    }

    public function headings(): array
    {
        return ['KPI', 'This Month', 'Last Month', 'Change'];
    }

    public function array(): array
    {
        $thisMonth = Order::whereMonth('created_at', now()->month);
        $lastMonth = Order::whereMonth('created_at', now()->subMonth()->month);
        
        return [
            [
                'Total Revenue',
                '$' . number_format($thisMonth->sum('total'), 2),
                '$' . number_format($lastMonth->sum('total'), 2),
                $this->calculateChange($thisMonth->sum('total'), $lastMonth->sum('total')),
            ],
            [
                'Orders',
                $thisMonth->count(),
                $lastMonth->count(),
                $this->calculateChange($thisMonth->count(), $lastMonth->count()),
            ],
            [
                'Avg Order Value',
                '$' . number_format($thisMonth->avg('total'), 2),
                '$' . number_format($lastMonth->avg('total'), 2),
                $this->calculateChange($thisMonth->avg('total'), $lastMonth->avg('total')),
            ],
        ];
    }

    protected function calculateChange($current, $previous): string
    {
        if ($previous == 0) return 'N/A';
        $change = (($current - $previous) / $previous) * 100;
        return ($change >= 0 ? '+' : '') . number_format($change, 1) . '%';
    }
}

class OrdersDetailSheet implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    use Exportable;

    public function title(): string
    {
        return 'Orders';
    }

    public function query()
    {
        return Order::query()
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->limit(1000);
    }

    public function headings(): array
    {
        return ['Order #', 'Customer', 'Status', 'Items', 'Total', 'Date'];
    }

    public function map($order): array
    {
        return [
            $order->order_number,
            $order->customer->name,
            ucfirst($order->status),
            $order->items_count,
            $order->total,
            $order->created_at->format('d-M-Y'),
        ];
    }
}

class TopProductsSheet implements FromQuery, WithHeadings, WithTitle
{
    use Exportable;

    public function title(): string
    {
        return 'Top Products';
    }

    public function query()
    {
        return Product::query()
            ->withCount('orderItems')
            ->orderBy('order_items_count', 'desc')
            ->limit(50);
    }

    public function headings(): array
    {
        return ['SKU', 'Product', 'Category', 'Price', 'Units Sold', 'Revenue'];
    }
}

class CustomerAnalysisSheet implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    use Exportable;

    public function title(): string
    {
        return 'Customers';
    }

    public function query()
    {
        return Customer::query()
            ->withCount('orders')
            ->withSum('orders', 'total')
            ->orderBy('orders_sum_total', 'desc')
            ->limit(100);
    }

    public function headings(): array
    {
        return ['Customer', 'Email', 'Total Orders', 'Total Spent', 'Avg Order'];
    }

    public function map($customer): array
    {
        $avgOrder = $customer->orders_count > 0
            ? $customer->orders_sum_total / $customer->orders_count
            : 0;
            
        return [
            $customer->name,
            $customer->email,
            $customer->orders_count,
            number_format($customer->orders_sum_total, 2),
            number_format($avgOrder, 2),
        ];
    }
}
```

Usage:

```php
return Excel::download(new ComprehensiveReportExport, 'full-report.xlsx');
```
