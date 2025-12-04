# Handling Large Datasets

This guide covers memory-efficient techniques for exporting large datasets (50K+ rows).

## Memory Comparison

| Method | Memory Usage | Rows | Best For |
|--------|--------------|------|----------|
| `FromCollection` | High | < 1K | Small datasets |
| `FromQuery` (cursor) | Medium | 1K - 50K | Medium datasets |
| `FromQuery` + Chunking | Low | 50K - 500K | Large datasets |
| Hybrid Exporter | Very Low | 100K+ | Very large datasets |

## Chunked Query Processing

### Fluent API

```php
use LaravelExporter\Facades\Exporter;

Exporter::make()
    ->format('xlsx')
    ->chunkSize(500)  // Process 500 rows at a time
    ->from(Order::query())
    ->toFile(storage_path('app/exports/large-orders.xlsx'));
```

### Class-Based Export

```php
<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\WithChunkReading;
use LaravelExporter\Concerns\Exportable;

class LargeOrdersExport implements FromQuery, WithHeadings, WithChunkReading
{
    use Exportable;

    public function query(): Builder
    {
        return Order::query();
    }

    public function headings(): array
    {
        return ['ID', 'Order Number', 'Total', 'Date'];
    }

    public function chunkSize(): int
    {
        return 1000;  // Process 1000 rows at a time
    }
}
```

## Using Generators

For custom iteration with minimal memory:

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
            // Process line and yield
            yield $this->parseLine($line);
        }
        
        fclose($handle);
    }

    protected function parseLine(string $line): array
    {
        // Parse and return row data
        return ['timestamp' => '', 'level' => '', 'message' => ''];
    }

    public function headings(): array
    {
        return ['Timestamp', 'Level', 'Message'];
    }
}
```

## Hybrid Exporter (100K+ Rows)

The Hybrid Exporter uses OpenSpout for streaming with XML post-processing for advanced features:

```php
<?php

namespace App\Exports;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\UseHybridExporter;
use LaravelExporter\Concerns\Exportable;

class HugeExport implements FromQuery, WithHeadings, UseHybridExporter
{
    use Exportable;

    public function query(): Builder
    {
        return Transaction::query();
    }

    public function headings(): array
    {
        return ['ID', 'Account', 'Type', 'Amount', 'Date'];
    }
}
```

**Memory Usage:**
- ~50MB for 100K rows WITH styling
- Supports freeze panes and auto-filter via XML manipulation

## Chunked PhpSpreadsheet Exporter

For large exports that need PhpSpreadsheet features:

```php
<?php

namespace App\Exports;

use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\UseChunkedWriter;
use LaravelExporter\Concerns\Exportable;

class ChunkedExport implements FromQuery, UseChunkedWriter
{
    use Exportable;

    public function query(): Builder
    {
        return Order::query();
    }
}
```

This streams data to a temp file in chunks, then post-processes with PhpSpreadsheet.

## Streaming Downloads

For web downloads, use streaming to prevent timeout:

```php
// In controller
public function exportLarge()
{
    return Exporter::make()
        ->format('csv')
        ->chunkSize(500)
        ->from(Order::query())
        ->stream('large-export.csv');  // Stream instead of download
}
```

## Database Query Optimization

### Use Select

Only select needed columns:

```php
Order::query()
    ->select(['id', 'order_number', 'total', 'created_at'])
    ->from($query);
```

### Avoid N+1

Eager load relationships:

```php
Order::query()
    ->with(['customer:id,name', 'items:id,order_id,quantity'])
    ->from($query);
```

### Use Indexes

Ensure database indexes on filtered/sorted columns:

```sql
CREATE INDEX orders_created_at_idx ON orders(created_at);
CREATE INDEX orders_status_idx ON orders(status);
```

## Memory Monitoring

Monitor memory usage during exports:

```php
use LaravelExporter\Facades\Exporter;

$startMemory = memory_get_peak_usage(true);

Exporter::make()
    ->from(Order::query())
    ->toFile('export.csv');

$endMemory = memory_get_peak_usage(true);

$memoryUsed = ($endMemory - $startMemory) / 1024 / 1024;
logger()->info("Export used {$memoryUsed}MB");
```

## PHP Configuration

Adjust PHP settings for large exports:

```php
// Increase memory limit (use sparingly)
ini_set('memory_limit', '512M');

// Increase execution time
set_time_limit(600);  // 10 minutes

// Or in php.ini
memory_limit = 512M
max_execution_time = 600
```

## Queue Large Exports

For very large exports, use queues:

```php
// Create a job
class ProcessLargeExport implements ShouldQueue
{
    public function handle()
    {
        Excel::store(
            new LargeOrdersExport,
            'exports/orders.xlsx',
            'local'
        );
        
        // Notify user
        $this->user->notify(new ExportReady('orders.xlsx'));
    }
}

// Dispatch from controller
ProcessLargeExport::dispatch($user);

return response()->json(['message' => 'Export started']);
```

## Best Practices

### 1. Choose the Right Exporter

```php
// < 10K rows: Standard exporter is fine
Exporter::make()->from(Order::query());

// 10K - 50K rows: Use chunking
Exporter::make()->chunkSize(1000)->from(Order::query());

// 50K+ rows: Use Hybrid or CSV
class Export implements FromQuery, UseHybridExporter { }
```

### 2. CSV for Very Large Files

CSV is the most memory-efficient format:

```php
Exporter::make()
    ->format('csv')
    ->options(['add_bom' => true])
    ->from(Order::query())
    ->toFile('huge-export.csv');
```

### 3. Limit Columns

Only export what's needed:

```php
Exporter::make()
    ->columns(['id', 'name', 'email'])  // Not all columns
    ->from(User::query());
```

### 4. Filter Before Export

Reduce data at the query level:

```php
Exporter::make()
    ->from(
        Order::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
    );
```

### 5. Use LazyCollections

```php
Exporter::make()
    ->from(User::lazy(1000))  // Cursor-based, low memory
    ->toFile('users.csv');
```

## Performance Benchmarks

Tested on a typical server (4 CPU, 8GB RAM):

| Rows | Format | Method | Memory | Time |
|------|--------|--------|--------|------|
| 10K | CSV | Standard | 32MB | 2s |
| 10K | XLSX | Standard | 64MB | 5s |
| 50K | CSV | Chunked | 48MB | 8s |
| 50K | XLSX | Chunked | 128MB | 25s |
| 100K | CSV | Chunked | 64MB | 15s |
| 100K | XLSX | Hybrid | 96MB | 45s |
| 500K | CSV | Chunked | 96MB | 60s |

## Troubleshooting

### Memory Exhausted

```
PHP Fatal error: Allowed memory size exhausted
```

**Solutions:**
1. Reduce chunk size
2. Use CSV instead of XLSX
3. Use LazyCollection
4. Increase memory_limit

### Timeout

```
Maximum execution time exceeded
```

**Solutions:**
1. Use queue for background processing
2. Increase max_execution_time
3. Use streaming download

### Slow Exports

**Solutions:**
1. Optimize database queries (indexes, select)
2. Reduce data volume with filters
3. Use SSD storage
4. Increase chunk size (if memory allows)
