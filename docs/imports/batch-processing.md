# Batch Processing & Performance

Efficient techniques for importing large files with optimal performance.

## Batch Inserts

Insert multiple records at once for better performance:

```php
<?php

namespace App\Imports;

use App\Models\Product;
use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Concerns\WithBatchInserts;
use LaravelExporter\Concerns\Importable;

class ProductsImport implements ToModel, WithHeadingRow, WithBatchInserts
{
    use Importable;

    public function model(array $row): Product
    {
        return new Product([
            'sku' => $row['sku'],
            'name' => $row['name'],
            'price' => $row['price'],
        ]);
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function batchSize(): int
    {
        return 500;  // Insert 500 records at a time
    }
}
```

### Performance Comparison

| Method | 10K Records | Memory |
|--------|-------------|--------|
| One-by-one | ~60 seconds | Low |
| Batch (100) | ~15 seconds | Low |
| Batch (500) | ~8 seconds | Medium |
| Batch (1000) | ~6 seconds | Higher |

## Upserts (Update or Create)

Update existing records or create new ones:

```php
<?php

namespace App\Imports;

use App\Models\Product;
use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Concerns\WithBatchInserts;
use LaravelExporter\Concerns\WithUpserts;
use LaravelExporter\Concerns\Importable;

class ProductsUpsertImport implements ToModel, WithHeadingRow, WithBatchInserts, WithUpserts
{
    use Importable;

    public function model(array $row): Product
    {
        return new Product([
            'sku' => $row['sku'],
            'name' => $row['name'],
            'price' => $row['price'],
            'stock' => $row['stock'],
        ]);
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function uniqueBy(): string
    {
        return 'sku';  // Unique column for upsert
    }
}
```

### Multiple Unique Columns

```php
public function uniqueBy(): array
{
    return ['sku', 'warehouse_id'];  // Composite unique key
}
```

## Chunk Reading

Read large files in chunks to reduce memory usage:

```php
<?php

namespace App\Imports;

use App\Models\Order;
use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Concerns\WithChunkReading;
use LaravelExporter\Concerns\Importable;

class LargeOrdersImport implements ToModel, WithHeadingRow, WithChunkReading
{
    use Importable;

    public function model(array $row): Order
    {
        return new Order([
            'order_number' => $row['order_number'],
            'total' => $row['total'],
        ]);
    }

    public function chunkSize(): int
    {
        return 1000;  // Read 1000 rows at a time
    }

    public function headingRow(): int
    {
        return 1;
    }
}
```

## Combining Batch & Chunk

For maximum efficiency with very large files:

```php
<?php

namespace App\Imports;

use App\Models\Transaction;
use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Concerns\WithChunkReading;
use LaravelExporter\Concerns\WithBatchInserts;
use LaravelExporter\Concerns\WithUpserts;
use LaravelExporter\Concerns\Importable;

class LargeTransactionImport implements
    ToModel,
    WithHeadingRow,
    WithChunkReading,
    WithBatchInserts,
    WithUpserts
{
    use Importable;

    public function model(array $row): Transaction
    {
        return new Transaction([
            'transaction_id' => $row['transaction_id'],
            'account_id' => $row['account_id'],
            'amount' => $row['amount'],
            'type' => $row['type'],
            'date' => $row['date'],
        ]);
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function chunkSize(): int
    {
        return 2000;  // Read 2000 rows from file at a time
    }

    public function batchSize(): int
    {
        return 500;   // Insert 500 records to database at a time
    }

    public function uniqueBy(): string
    {
        return 'transaction_id';
    }
}
```

### Memory Usage

| File Size | Without Chunking | With Chunking |
|-----------|------------------|---------------|
| 10K rows | ~64MB | ~32MB |
| 50K rows | ~256MB | ~48MB |
| 100K rows | ~512MB | ~64MB |

## Disable Query Log

For large imports, disable query logging:

```php
use Illuminate\Support\Facades\DB;

DB::disableQueryLog();

Excel::import(new LargeImport, 'huge-file.xlsx');

DB::enableQueryLog();
```

## Transactions

Wrap imports in a transaction for data integrity:

```php
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    Excel::import(new ProductsImport, 'products.xlsx');
});
```

### Chunked Transactions

For large files, use transactions per chunk:

```php
<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\WithChunkReading;

class ChunkedTransactionImport implements ToModel, WithChunkReading
{
    protected int $chunkNumber = 0;

    public function model(array $row): Model
    {
        return new Product([...]);
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    // Called before each chunk
    public function beforeChunk(): void
    {
        DB::beginTransaction();
    }

    // Called after each chunk
    public function afterChunk(): void
    {
        DB::commit();
        $this->chunkNumber++;
        
        logger()->info("Processed chunk {$this->chunkNumber}");
    }
}
```

## Progress Tracking

Track import progress:

```php
<?php

namespace App\Imports;

use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Concerns\WithProgressBar;
use LaravelExporter\Concerns\RemembersRowNumber;
use LaravelExporter\Concerns\Importable;

class TrackedImport implements ToModel, WithHeadingRow, WithProgressBar, RemembersRowNumber
{
    use Importable;

    protected int $processed = 0;

    public function model(array $row): Model
    {
        $this->processed++;
        
        // Log progress every 1000 rows
        if ($this->processed % 1000 === 0) {
            logger()->info("Processed {$this->processed} rows");
        }
        
        return new Product([...]);
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }
}
```

### Console Progress Bar

```php
use Illuminate\Console\Command;

class ImportCommand extends Command
{
    protected $signature = 'import:products {file}';

    public function handle()
    {
        $file = $this->argument('file');
        
        $this->info('Starting import...');
        
        $import = new ProductsImport;
        
        Excel::import($import, $file);
        
        $this->info("Imported {$import->getProcessed()} rows");
    }
}
```

## Optimized Model Creation

### Disable Timestamps

```php
public function model(array $row): Product
{
    $product = new Product([
        'sku' => $row['sku'],
        'name' => $row['name'],
    ]);
    
    $product->timestamps = false;  // Disable auto timestamps
    
    return $product;
}
```

### Skip Events

```php
public function model(array $row): Product
{
    return Product::withoutEvents(function () use ($row) {
        return new Product([
            'sku' => $row['sku'],
            'name' => $row['name'],
        ]);
    });
}
```

## Database Optimization

### Disable Foreign Key Checks

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Schema::disableForeignKeyConstraints();

Excel::import(new LargeImport, 'file.xlsx');

Schema::enableForeignKeyConstraints();
```

### Truncate Before Import

```php
use App\Models\Product;

Product::truncate();

Excel::import(new ProductsImport, 'products.xlsx');
```

## Queue Large Imports

For very large files, use queue:

```php
<?php

namespace App\Jobs;

use App\Imports\LargeProductsImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LaravelExporter\Facades\Excel;

class ProcessLargeImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;  // 1 hour
    public int $tries = 1;

    protected string $filePath;
    protected int $userId;

    public function __construct(string $filePath, int $userId)
    {
        $this->filePath = $filePath;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        $import = new LargeProductsImport;
        
        $result = Excel::import($import, $this->filePath);
        
        // Notify user
        $user = User::find($this->userId);
        $user->notify(new ImportCompleted($result));
        
        // Cleanup
        Storage::delete($this->filePath);
    }

    public function failed(Throwable $exception): void
    {
        $user = User::find($this->userId);
        $user->notify(new ImportFailed($exception->getMessage()));
    }
}
```

Usage:

```php
public function import(Request $request)
{
    $path = $request->file('file')->store('imports');
    
    ProcessLargeImport::dispatch($path, auth()->id());
    
    return response()->json([
        'message' => 'Import queued. You will be notified when complete.',
    ]);
}
```

## Complete High-Performance Import

```php
<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Concerns\WithChunkReading;
use LaravelExporter\Concerns\WithBatchInserts;
use LaravelExporter\Concerns\WithUpserts;
use LaravelExporter\Concerns\WithValidation;
use LaravelExporter\Concerns\SkipsOnFailure;
use LaravelExporter\Concerns\RemembersRowNumber;
use LaravelExporter\Imports\Failure;
use LaravelExporter\Concerns\Importable;

class HighPerformanceImport implements
    ToModel,
    WithHeadingRow,
    WithChunkReading,
    WithBatchInserts,
    WithUpserts,
    WithValidation,
    SkipsOnFailure,
    RemembersRowNumber
{
    use Importable;

    protected array $failures = [];
    protected int $processed = 0;
    protected int $startTime;

    public function __construct()
    {
        $this->startTime = time();
        
        // Optimize for large imports
        DB::disableQueryLog();
    }

    public function model(array $row): Product
    {
        $this->processed++;
        
        $product = new Product([
            'sku' => $row['sku'],
            'name' => $row['name'],
            'description' => $row['description'] ?? null,
            'price' => $this->parsePrice($row['price']),
            'stock' => (int) $row['stock'],
            'category_id' => $row['category_id'],
        ]);
        
        $product->timestamps = false;
        
        return $product;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function chunkSize(): int
    {
        return 2000;
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function uniqueBy(): string
    {
        return 'sku';
    }

    public function rules(): array
    {
        return [
            'sku' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ];
    }

    public function onFailure(Failure ...$failures): void
    {
        $this->failures = array_merge($this->failures, $failures);
    }

    public function getFailures(): array
    {
        return $this->failures;
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }

    public function getDuration(): int
    {
        return time() - $this->startTime;
    }

    protected function parsePrice($value): float
    {
        return (float) str_replace(['$', ',', ' '], '', $value);
    }
}
```

Usage:

```php
$import = new HighPerformanceImport;
$result = Excel::import($import, 'products.xlsx');

echo "Processed: {$import->getProcessed()} rows\n";
echo "Duration: {$import->getDuration()} seconds\n";
echo "Failures: " . count($import->getFailures()) . "\n";
echo "Memory: " . $result->peakMemoryFormatted() . "\n";
```
