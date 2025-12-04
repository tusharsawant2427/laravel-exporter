# Performance Optimization

This guide covers strategies for optimizing export and import performance, especially when dealing with large datasets.

## Table of Contents

1. [Understanding Memory Usage](#understanding-memory-usage)
2. [Choosing the Right Exporter](#choosing-the-right-exporter)
3. [Query Optimization](#query-optimization)
4. [Chunking Strategies](#chunking-strategies)
5. [Generator-Based Exports](#generator-based-exports)
6. [Caching Strategies](#caching-strategies)
7. [Queue Processing](#queue-processing)
8. [Monitoring and Profiling](#monitoring-and-profiling)
9. [Best Practices Summary](#best-practices-summary)

---

## Understanding Memory Usage

### Memory Consumption by Format

| Format | Memory Usage | Speed | Features |
|--------|--------------|-------|----------|
| CSV | Very Low | Fastest | No styling |
| Native XLSX (OpenSpout) | Low | Fast | Basic styling |
| PhpSpreadsheet | High | Slow | Full styling |
| Hybrid | Medium | Medium | Balanced |

### Memory Calculation

Approximate memory per row:

```
CSV: ~200 bytes/row
Native XLSX: ~500 bytes/row
PhpSpreadsheet: ~2-5 KB/row (in memory)
```

For 100,000 rows:
- CSV: ~20 MB
- Native XLSX: ~50 MB
- PhpSpreadsheet: ~200-500 MB

### Checking Memory Usage

```php
use Illuminate\Support\Facades\Log;

class MemoryAwareExport implements FromQuery, WithEvents
{
    public function registerEvents(): array
    {
        return [
            BeforeExport::class => function() {
                Log::info('Export started', [
                    'memory' => memory_get_usage(true) / 1024 / 1024 . ' MB',
                ]);
            },
            AfterSheet::class => function() {
                Log::info('Sheet completed', [
                    'memory' => memory_get_usage(true) / 1024 / 1024 . ' MB',
                    'peak' => memory_get_peak_usage(true) / 1024 / 1024 . ' MB',
                ]);
            },
        ];
    }
}
```

---

## Choosing the Right Exporter

### Decision Tree

```
Do you need styling (colors, borders, formatting)?
├── No → Use CSV or Native XLSX
│   └── Is speed critical?
│       ├── Yes → CSV
│       └── No → Native XLSX
└── Yes → How many rows?
    ├── < 10,000 → PhpSpreadsheet
    ├── 10,000 - 100,000 → Hybrid Exporter
    └── > 100,000 → Consider splitting or Hybrid
```

### Configuration for Each Scenario

#### Scenario 1: Simple Data Export (No Styling)

```php
// config/exporter.php
return [
    'default_format' => 'csv',  // or 'native-xlsx'
];

// Or per-export
return Exporter::make()
    ->data($data)
    ->format('csv')
    ->download();
```

#### Scenario 2: Styled Reports Under 10K Rows

```php
class SmallStyledExport implements FromCollection, WithStyles
{
    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
        ];
    }
}

// Uses PhpSpreadsheet automatically
Excel::download(new SmallStyledExport(), 'report.xlsx');
```

#### Scenario 3: Large Styled Reports (10K-100K Rows)

```php
class LargeStyledExport implements 
    FromQuery,
    WithColumnDefinitions,
    WithConditionalColoring,
    WithReportHeader,
    WithTotals
{
    // Implement interfaces...
    
    public function chunkSize(): int
    {
        return 5000;
    }
}

// config/exporter.php
return [
    'use_hybrid_exporter' => true,
    'hybrid_threshold' => 10000,
];
```

#### Scenario 4: Massive Exports (100K+ Rows)

```php
class MassiveExport implements FromQuery, WithChunkReading
{
    // Use chunking and consider:
    // 1. Splitting into multiple files
    // 2. Background processing
    // 3. CSV format
    
    public function chunkSize(): int
    {
        return 10000;
    }
}

// Background processing
Excel::queue(new MassiveExport(), 'exports/massive.csv')
    ->chain([
        new NotifyUserOfExportCompletion(),
    ]);
```

---

## Query Optimization

### Eager Loading

**Bad:**
```php
public function query()
{
    return Order::query();  // N+1 when accessing relationships
}

public function map($order): array
{
    return [
        $order->customer->name,  // Additional query per row
        $order->items->count(),  // Additional query per row
    ];
}
```

**Good:**
```php
public function query()
{
    return Order::query()
        ->with(['customer', 'items'])  // Eager load
        ->withCount('items');  // Count in single query
}

public function map($order): array
{
    return [
        $order->customer->name,  // Already loaded
        $order->items_count,     // Already calculated
    ];
}
```

### Select Only Needed Columns

```php
public function query()
{
    return Order::query()
        ->select([
            'orders.id',
            'orders.order_number',
            'orders.total',
            'orders.created_at',
            'orders.customer_id',
        ])
        ->with([
            'customer:id,name,email',  // Select specific columns
        ]);
}
```

### Use Database Aggregations

```php
// Instead of loading all items to sum in PHP
public function query()
{
    return Order::query()
        ->withSum('items', 'quantity')
        ->withSum('items', 'price')
        ->withAvg('items', 'price');
}

public function map($order): array
{
    return [
        $order->items_sum_quantity,  // Database calculated
        $order->items_sum_price,     // Database calculated
        $order->items_avg_price,     // Database calculated
    ];
}
```

### Avoid DateTime Parsing in Loop

```php
// Slow - parsing in every iteration
public function map($order): array
{
    return [
        Carbon::parse($order->created_at)->format('Y-m-d'),
    ];
}

// Fast - let the database format or use attribute casting
public function query()
{
    return Order::query()
        ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') as formatted_date");
}

// Or use model casting
class Order extends Model
{
    protected $casts = [
        'created_at' => 'datetime:Y-m-d',
    ];
}
```

---

## Chunking Strategies

### Optimal Chunk Sizes

| Row Size (bytes) | Recommended Chunk Size |
|-----------------|----------------------|
| < 500 | 5,000 - 10,000 |
| 500 - 1,000 | 2,000 - 5,000 |
| 1,000 - 5,000 | 500 - 2,000 |
| > 5,000 | 100 - 500 |

### Implementing Chunking

```php
class ChunkedExport implements FromQuery, WithChunkReading
{
    public function query()
    {
        return Order::query()->orderBy('id');  // Consistent ordering
    }

    public function chunkSize(): int
    {
        // Calculate based on row size
        $estimatedRowSize = 500;  // bytes
        $targetMemory = 50 * 1024 * 1024;  // 50MB per chunk
        
        return (int) ($targetMemory / $estimatedRowSize);
    }
}
```

### Cursor-Based Chunking for Very Large Datasets

```php
class CursorExport implements FromQuery
{
    public function query()
    {
        return Order::query()
            ->orderBy('id')
            ->cursor();  // Uses PHP generator, minimal memory
    }
}
```

---

## Generator-Based Exports

Generators allow processing one row at a time, keeping memory constant.

### Basic Generator Export

```php
class GeneratorExport implements FromGenerator, WithHeadings
{
    public function generator(): Generator
    {
        $query = Order::query()
            ->with('customer')
            ->orderBy('id');

        foreach ($query->cursor() as $order) {
            yield [
                $order->order_number,
                $order->customer->name,
                $order->total,
                $order->created_at->format('Y-m-d'),
            ];
        }
    }

    public function headings(): array
    {
        return ['Order #', 'Customer', 'Total', 'Date'];
    }
}
```

### Generator with Progress Tracking

```php
class TrackedGeneratorExport implements FromGenerator
{
    protected int $processed = 0;
    protected int $total;

    public function __construct()
    {
        $this->total = Order::count();
    }

    public function generator(): Generator
    {
        foreach (Order::cursor() as $order) {
            $this->processed++;
            
            // Log progress every 1000 rows
            if ($this->processed % 1000 === 0) {
                Log::info("Export progress: {$this->processed}/{$this->total}");
            }

            yield $this->mapOrder($order);
        }
    }

    protected function mapOrder(Order $order): array
    {
        return [/* ... */];
    }
}
```

### Combining Generator with LazyCollection

```php
use Illuminate\Support\LazyCollection;

public function generator(): Generator
{
    yield from Order::query()
        ->with('customer')
        ->lazy(1000)  // Fetch 1000 at a time
        ->map(fn($order) => [
            $order->order_number,
            $order->customer->name,
            $order->total,
        ]);
}
```

---

## Caching Strategies

### Cache Lookup Data

```php
class OptimizedExport implements FromQuery, WithMapping
{
    protected array $categoryCache = [];
    protected array $userCache = [];

    public function __construct()
    {
        // Pre-load lookup data
        $this->categoryCache = Category::pluck('name', 'id')->toArray();
        $this->userCache = User::pluck('name', 'id')->toArray();
    }

    public function map($order): array
    {
        return [
            $order->id,
            $this->categoryCache[$order->category_id] ?? 'Unknown',
            $this->userCache[$order->user_id] ?? 'Unknown',
        ];
    }
}
```

### Cache Expensive Calculations

```php
class AnalyticsExport implements FromCollection
{
    public function collection()
    {
        return Cache::remember('analytics_export_' . today()->format('Y-m-d'), 3600, function () {
            return $this->calculateAnalytics();
        });
    }

    protected function calculateAnalytics(): Collection
    {
        // Expensive calculations...
    }
}
```

### Store Generated Files

```php
class CachedReportExport
{
    public function download()
    {
        $cacheKey = 'report_' . md5(serialize($this->filters)) . '_' . today()->format('Y-m-d');
        $filePath = "cache/reports/{$cacheKey}.xlsx";

        if (!Storage::exists($filePath)) {
            Excel::store(new ReportExport($this->filters), $filePath);
        }

        return Storage::download($filePath, 'report.xlsx');
    }
}
```

---

## Queue Processing

### Basic Queued Export

```php
use DataSuite\LaravelExporter\Facades\Excel;

class ExportController extends Controller
{
    public function export(Request $request)
    {
        $user = $request->user();
        $filename = 'exports/' . Str::uuid() . '.xlsx';

        Excel::queue(new LargeExport(), $filename, 's3')
            ->chain([
                new NotifyUserOfExport($user, $filename),
            ]);

        return response()->json([
            'message' => 'Export started. You will receive an email when complete.',
        ]);
    }
}
```

### Notification Job

```php
class NotifyUserOfExport implements ShouldQueue
{
    public function __construct(
        protected User $user,
        protected string $filename
    ) {}

    public function handle()
    {
        $url = Storage::disk('s3')->temporaryUrl($this->filename, now()->addDay());

        $this->user->notify(new ExportReadyNotification($url));
    }
}
```

### Progress Tracking with Database

```php
// Migration
Schema::create('export_jobs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id');
    $table->string('type');
    $table->string('status')->default('pending');
    $table->integer('progress')->default(0);
    $table->integer('total')->nullable();
    $table->string('file_path')->nullable();
    $table->json('errors')->nullable();
    $table->timestamps();
});

// Export with tracking
class TrackedExport implements FromQuery, WithEvents
{
    public function __construct(
        protected ExportJob $job
    ) {}

    public function registerEvents(): array
    {
        return [
            BeforeExport::class => fn() => $this->job->update(['status' => 'processing']),
            AfterChunk::class => function($event) {
                $this->job->increment('progress', $event->count);
            },
            AfterExport::class => fn() => $this->job->update([
                'status' => 'completed',
                'file_path' => $this->job->file_path,
            ]),
        ];
    }
}

// Controller
public function startExport(Request $request)
{
    $job = ExportJob::create([
        'id' => Str::uuid(),
        'user_id' => $request->user()->id,
        'type' => 'orders',
        'total' => Order::count(),
    ]);

    Excel::queue(new TrackedExport($job), "exports/{$job->id}.xlsx");

    return response()->json(['job_id' => $job->id]);
}

public function checkProgress(string $jobId)
{
    $job = ExportJob::findOrFail($jobId);

    return response()->json([
        'status' => $job->status,
        'progress' => $job->progress,
        'total' => $job->total,
        'percentage' => $job->total ? round(($job->progress / $job->total) * 100) : 0,
        'download_url' => $job->status === 'completed' 
            ? Storage::temporaryUrl($job->file_path, now()->addHour())
            : null,
    ]);
}
```

---

## Monitoring and Profiling

### Laravel Telescope Integration

```php
use Laravel\Telescope\Telescope;

class MonitoredExport implements FromQuery, WithEvents
{
    public function registerEvents(): array
    {
        return [
            BeforeExport::class => function() {
                Telescope::tag(['export', 'orders']);
            },
        ];
    }
}
```

### Custom Metrics

```php
class MetricExport implements FromQuery, WithEvents
{
    protected float $startTime;
    protected int $startMemory;

    public function registerEvents(): array
    {
        return [
            BeforeExport::class => function() {
                $this->startTime = microtime(true);
                $this->startMemory = memory_get_usage();
            },
            AfterExport::class => function() {
                $duration = microtime(true) - $this->startTime;
                $memoryUsed = memory_get_usage() - $this->startMemory;

                // Log to your metrics system
                Metrics::timing('exports.duration', $duration);
                Metrics::gauge('exports.memory', $memoryUsed);

                Log::info('Export completed', [
                    'duration' => round($duration, 2) . 's',
                    'memory' => round($memoryUsed / 1024 / 1024, 2) . 'MB',
                    'peak_memory' => round(memory_get_peak_usage() / 1024 / 1024, 2) . 'MB',
                ]);
            },
        ];
    }
}
```

### Database Query Monitoring

```php
use Illuminate\Support\Facades\DB;

class QueryMonitoredExport implements FromQuery
{
    public function query()
    {
        DB::enableQueryLog();

        $query = Order::query()->with('customer');

        // After export
        register_shutdown_function(function() {
            $queries = DB::getQueryLog();
            Log::info('Export queries', [
                'count' => count($queries),
                'total_time' => collect($queries)->sum('time'),
            ]);
        });

        return $query;
    }
}
```

---

## Best Practices Summary

### Do's

1. **Use eager loading** - Always `with()` relationships you'll access
2. **Select only needed columns** - Don't load entire models if you don't need them
3. **Use chunking** - For any export over 1,000 rows
4. **Use generators** - For exports over 10,000 rows
5. **Cache lookup data** - Pre-load categories, statuses, etc.
6. **Queue large exports** - Anything over 10,000 rows should be queued
7. **Monitor memory** - Track peak memory usage
8. **Choose appropriate format** - CSV for simple data, native XLSX for basic styling

### Don'ts

1. **Don't load all records at once** - Use `cursor()` or chunking
2. **Don't use PhpSpreadsheet for large files** - Use hybrid or native exporters
3. **Don't parse dates in loops** - Use model casting or database formatting
4. **Don't make queries in `map()` methods** - Pre-load all needed data
5. **Don't ignore memory limits** - Set appropriate PHP limits or use streaming
6. **Don't skip testing with production-size data** - Test with realistic volumes

### Quick Optimization Checklist

```
□ Eager load all relationships used in mapping
□ Select only required columns
□ Use chunk size appropriate for row size
□ Use generators for large exports
□ Cache lookup data that's accessed repeatedly
□ Queue exports over 10K rows
□ Choose format based on requirements (CSV > Native XLSX > PhpSpreadsheet)
□ Monitor memory usage during development
□ Test with production-size data
```

---

[← Back to Documentation](../INDEX.md) | [Custom Exporters →](./custom-exporters.md)
