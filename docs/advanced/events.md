# Events

The Laravel Exporter package dispatches events at various points during the export and import lifecycle, allowing you to hook into the process for logging, monitoring, or custom behavior.

## Table of Contents

1. [Export Events](#export-events)
2. [Import Events](#import-events)
3. [Registering Event Listeners](#registering-event-listeners)
4. [Using Event Subscribers](#using-event-subscribers)
5. [Common Use Cases](#common-use-cases)

---

## Export Events

### Available Export Events

| Event | Description | Available Data |
|-------|-------------|----------------|
| `BeforeExport` | Before export starts | Export object |
| `BeforeSheet` | Before each sheet is created | Sheet name, export object |
| `AfterSheet` | After each sheet is complete | Sheet object, row count |
| `AfterChunk` | After each chunk is processed | Chunk number, count |
| `AfterExport` | After export completes | File path, export object |

### Event Classes

```php
namespace DataSuite\LaravelExporter\Events;

class BeforeExport
{
    public function __construct(
        public readonly object $exportable,
        public readonly array $options = []
    ) {}
}

class BeforeSheet
{
    public function __construct(
        public readonly object $exportable,
        public readonly string $sheetName,
        public readonly int $sheetIndex
    ) {}
}

class AfterSheet
{
    public function __construct(
        public readonly object $exportable,
        public readonly string $sheetName,
        public readonly int $rowCount,
        public readonly int $sheetIndex
    ) {}
}

class AfterChunk
{
    public function __construct(
        public readonly object $exportable,
        public readonly int $chunkNumber,
        public readonly int $processedCount,
        public readonly int $totalProcessed
    ) {}
}

class AfterExport
{
    public function __construct(
        public readonly object $exportable,
        public readonly string $filePath,
        public readonly int $totalRows,
        public readonly float $duration
    ) {}
}
```

### Implementing WithEvents

```php
<?php

namespace App\Exports;

use DataSuite\LaravelExporter\Concerns\FromQuery;
use DataSuite\LaravelExporter\Concerns\WithEvents;
use DataSuite\LaravelExporter\Events\BeforeExport;
use DataSuite\LaravelExporter\Events\AfterSheet;
use DataSuite\LaravelExporter\Events\AfterExport;
use Illuminate\Support\Facades\Log;

class OrdersExport implements FromQuery, WithEvents
{
    protected float $startTime;
    protected int $rowCount = 0;

    public function query()
    {
        return Order::query()->with('customer');
    }

    public function registerEvents(): array
    {
        return [
            BeforeExport::class => function (BeforeExport $event) {
                $this->startTime = microtime(true);
                
                Log::info('Export started', [
                    'export' => get_class($event->exportable),
                    'memory' => memory_get_usage(true),
                ]);
            },

            AfterSheet::class => function (AfterSheet $event) {
                $this->rowCount += $event->rowCount;
                
                Log::info('Sheet completed', [
                    'sheet' => $event->sheetName,
                    'rows' => $event->rowCount,
                ]);
            },

            AfterExport::class => function (AfterExport $event) {
                $duration = microtime(true) - $this->startTime;
                
                Log::info('Export completed', [
                    'file' => $event->filePath,
                    'rows' => $event->totalRows,
                    'duration' => round($duration, 2) . 's',
                    'memory_peak' => memory_get_peak_usage(true),
                ]);

                // Notify admin of large exports
                if ($event->totalRows > 10000) {
                    $this->notifyAdminOfLargeExport($event);
                }
            },
        ];
    }

    protected function notifyAdminOfLargeExport(AfterExport $event): void
    {
        Notification::route('mail', config('app.admin_email'))
            ->notify(new LargeExportCompleted($event));
    }
}
```

---

## Import Events

### Available Import Events

| Event | Description | Available Data |
|-------|-------------|----------------|
| `BeforeImport` | Before import starts | Import object, file path |
| `BeforeSheet` | Before each sheet is read | Sheet name, import object |
| `AfterSheet` | After each sheet is processed | Sheet name, row count |
| `AfterChunk` | After each chunk is imported | Chunk number, count |
| `AfterImport` | After import completes | Import object, statistics |
| `ImportFailed` | When import fails | Exception, partial results |

### Event Classes

```php
namespace DataSuite\LaravelExporter\Events;

class BeforeImport
{
    public function __construct(
        public readonly object $importable,
        public readonly string $filePath
    ) {}
}

class AfterImport
{
    public function __construct(
        public readonly object $importable,
        public readonly int $totalRows,
        public readonly int $successCount,
        public readonly int $failureCount,
        public readonly float $duration
    ) {}
}

class ImportFailed
{
    public function __construct(
        public readonly object $importable,
        public readonly \Throwable $exception,
        public readonly int $processedRows
    ) {}
}
```

### Implementing WithEvents for Imports

```php
<?php

namespace App\Imports;

use DataSuite\LaravelExporter\Concerns\ToModel;
use DataSuite\LaravelExporter\Concerns\WithEvents;
use DataSuite\LaravelExporter\Concerns\WithHeadingRow;
use DataSuite\LaravelExporter\Events\BeforeImport;
use DataSuite\LaravelExporter\Events\AfterImport;
use DataSuite\LaravelExporter\Events\ImportFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProductsImport implements ToModel, WithHeadingRow, WithEvents
{
    protected string $importId;

    public function __construct()
    {
        $this->importId = (string) Str::uuid();
    }

    public function model(array $row)
    {
        return new Product([
            'sku' => $row['sku'],
            'name' => $row['name'],
            'price' => $row['price'],
        ]);
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                Log::info('Import started', [
                    'import_id' => $this->importId,
                    'file' => $event->filePath,
                ]);

                // Store import status for tracking
                Cache::put("import:{$this->importId}", [
                    'status' => 'processing',
                    'started_at' => now(),
                    'processed' => 0,
                ], now()->addHours(24));
            },

            AfterChunk::class => function ($event) {
                // Update progress
                Cache::put("import:{$this->importId}", [
                    'status' => 'processing',
                    'processed' => $event->totalProcessed,
                ], now()->addHours(24));
            },

            AfterImport::class => function (AfterImport $event) {
                Log::info('Import completed', [
                    'import_id' => $this->importId,
                    'total' => $event->totalRows,
                    'success' => $event->successCount,
                    'failures' => $event->failureCount,
                    'duration' => $event->duration,
                ]);

                Cache::put("import:{$this->importId}", [
                    'status' => 'completed',
                    'total' => $event->totalRows,
                    'success' => $event->successCount,
                    'failures' => $event->failureCount,
                ], now()->addHours(24));
            },

            ImportFailed::class => function (ImportFailed $event) {
                Log::error('Import failed', [
                    'import_id' => $this->importId,
                    'error' => $event->exception->getMessage(),
                    'processed' => $event->processedRows,
                ]);

                Cache::put("import:{$this->importId}", [
                    'status' => 'failed',
                    'error' => $event->exception->getMessage(),
                    'processed' => $event->processedRows,
                ], now()->addHours(24));
            },
        ];
    }

    public function getImportId(): string
    {
        return $this->importId;
    }
}
```

---

## Registering Event Listeners

### Method 1: Using Event Service Provider

```php
<?php

namespace App\Providers;

use DataSuite\LaravelExporter\Events\AfterExport;
use DataSuite\LaravelExporter\Events\AfterImport;
use App\Listeners\LogExportCompletion;
use App\Listeners\LogImportCompletion;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        AfterExport::class => [
            LogExportCompletion::class,
            NotifyUserOfExport::class,
            CleanupTempFiles::class,
        ],
        AfterImport::class => [
            LogImportCompletion::class,
            SendImportSummary::class,
        ],
    ];
}
```

### Listener Class

```php
<?php

namespace App\Listeners;

use DataSuite\LaravelExporter\Events\AfterExport;
use Illuminate\Support\Facades\Log;

class LogExportCompletion
{
    public function handle(AfterExport $event): void
    {
        Log::channel('exports')->info('Export completed', [
            'export_class' => get_class($event->exportable),
            'file_path' => $event->filePath,
            'total_rows' => $event->totalRows,
            'duration_seconds' => $event->duration,
            'user_id' => auth()->id(),
        ]);
    }
}
```

### Method 2: Using Closures in Boot

```php
<?php

namespace App\Providers;

use DataSuite\LaravelExporter\Events\AfterExport;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Event::listen(AfterExport::class, function (AfterExport $event) {
            // Quick logging
            logger()->info("Export completed: {$event->filePath}");
        });
    }
}
```

### Method 3: Queueable Listeners

```php
<?php

namespace App\Listeners;

use DataSuite\LaravelExporter\Events\AfterExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessExportMetrics implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'metrics';

    public function handle(AfterExport $event): void
    {
        // Send to metrics system
        Metrics::timing('export.duration', $event->duration);
        Metrics::count('export.rows', $event->totalRows);
        Metrics::increment('export.completed');
    }

    public function shouldQueue(AfterExport $event): bool
    {
        // Only queue for large exports
        return $event->totalRows > 1000;
    }
}
```

---

## Using Event Subscribers

For complex event handling, use a subscriber class:

```php
<?php

namespace App\Listeners;

use DataSuite\LaravelExporter\Events\BeforeExport;
use DataSuite\LaravelExporter\Events\AfterExport;
use DataSuite\LaravelExporter\Events\BeforeImport;
use DataSuite\LaravelExporter\Events\AfterImport;
use DataSuite\LaravelExporter\Events\ImportFailed;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ExporterEventSubscriber
{
    protected array $exportStartTimes = [];
    protected array $importStartTimes = [];

    public function handleBeforeExport(BeforeExport $event): void
    {
        $key = spl_object_id($event->exportable);
        $this->exportStartTimes[$key] = microtime(true);

        Log::channel('exports')->info('Export starting', [
            'class' => get_class($event->exportable),
            'user' => auth()->user()?->email,
        ]);
    }

    public function handleAfterExport(AfterExport $event): void
    {
        $key = spl_object_id($event->exportable);
        $startTime = $this->exportStartTimes[$key] ?? null;

        // Store in database for analytics
        DB::table('export_logs')->insert([
            'export_class' => get_class($event->exportable),
            'file_path' => $event->filePath,
            'row_count' => $event->totalRows,
            'duration' => $event->duration,
            'memory_peak' => memory_get_peak_usage(true),
            'user_id' => auth()->id(),
            'created_at' => now(),
        ]);

        unset($this->exportStartTimes[$key]);
    }

    public function handleBeforeImport(BeforeImport $event): void
    {
        $key = spl_object_id($event->importable);
        $this->importStartTimes[$key] = microtime(true);

        Log::channel('imports')->info('Import starting', [
            'class' => get_class($event->importable),
            'file' => $event->filePath,
            'user' => auth()->user()?->email,
        ]);
    }

    public function handleAfterImport(AfterImport $event): void
    {
        $key = spl_object_id($event->importable);

        DB::table('import_logs')->insert([
            'import_class' => get_class($event->importable),
            'total_rows' => $event->totalRows,
            'success_count' => $event->successCount,
            'failure_count' => $event->failureCount,
            'duration' => $event->duration,
            'user_id' => auth()->id(),
            'created_at' => now(),
        ]);

        unset($this->importStartTimes[$key]);
    }

    public function handleImportFailed(ImportFailed $event): void
    {
        Log::channel('imports')->error('Import failed', [
            'class' => get_class($event->importable),
            'error' => $event->exception->getMessage(),
            'trace' => $event->exception->getTraceAsString(),
            'processed_rows' => $event->processedRows,
        ]);

        // Alert admin
        Notification::route('slack', config('logging.channels.slack.url'))
            ->notify(new ImportFailedNotification($event));
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            BeforeExport::class => 'handleBeforeExport',
            AfterExport::class => 'handleAfterExport',
            BeforeImport::class => 'handleBeforeImport',
            AfterImport::class => 'handleAfterImport',
            ImportFailed::class => 'handleImportFailed',
        ];
    }
}
```

### Register the Subscriber

```php
// App\Providers\EventServiceProvider

protected $subscribe = [
    \App\Listeners\ExporterEventSubscriber::class,
];
```

---

## Common Use Cases

### 1. Progress Tracking with WebSockets

```php
use DataSuite\LaravelExporter\Concerns\WithEvents;
use DataSuite\LaravelExporter\Events\AfterChunk;
use Illuminate\Support\Facades\Broadcast;

class TrackedExport implements FromQuery, WithEvents
{
    public function __construct(
        protected string $jobId,
        protected int $userId
    ) {}

    public function registerEvents(): array
    {
        return [
            AfterChunk::class => function (AfterChunk $event) {
                // Broadcast progress to user's channel
                broadcast(new ExportProgress(
                    $this->userId,
                    $this->jobId,
                    $event->totalProcessed,
                    $this->getExpectedTotal()
                ));
            },
        ];
    }
}

// ExportProgress event
class ExportProgress implements ShouldBroadcast
{
    public function __construct(
        public int $userId,
        public string $jobId,
        public int $processed,
        public int $total
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'export.progress';
    }
}
```

### 2. Automatic Cleanup

```php
class CleanupAfterExport
{
    public function handle(AfterExport $event): void
    {
        // Delete temp files older than 1 hour
        $tempPath = storage_path('app/temp/exports');
        
        collect(File::files($tempPath))
            ->filter(fn($file) => $file->getMTime() < now()->subHour()->timestamp)
            ->each(fn($file) => File::delete($file->getPathname()));
    }
}
```

### 3. Export Quota Enforcement

```php
class EnforceExportQuota
{
    public function handle(BeforeExport $event): void
    {
        $user = auth()->user();
        
        $exportCount = Cache::get("exports:{$user->id}:" . today()->format('Y-m-d'), 0);
        $dailyLimit = $user->export_limit ?? 100;

        if ($exportCount >= $dailyLimit) {
            throw new ExportQuotaExceededException(
                "Daily export limit of {$dailyLimit} reached."
            );
        }

        Cache::increment("exports:{$user->id}:" . today()->format('Y-m-d'));
    }
}
```

### 4. Audit Logging

```php
class AuditExportActivity
{
    public function handle(AfterExport $event): void
    {
        activity()
            ->performedOn(null)
            ->causedBy(auth()->user())
            ->withProperties([
                'export_class' => get_class($event->exportable),
                'file_path' => $event->filePath,
                'row_count' => $event->totalRows,
                'duration' => $event->duration,
                'ip_address' => request()->ip(),
            ])
            ->log('exported_data');
    }
}
```

### 5. Automatic Virus Scanning for Imports

```php
class ScanImportForViruses
{
    public function handle(BeforeImport $event): void
    {
        $scanner = app(VirusScanner::class);
        
        $result = $scanner->scan($event->filePath);
        
        if (!$result->isClean()) {
            // Delete infected file
            File::delete($event->filePath);
            
            // Log incident
            Log::channel('security')->warning('Infected file upload blocked', [
                'file' => $event->filePath,
                'user' => auth()->user()?->email,
                'threat' => $result->getThreatName(),
            ]);
            
            throw new InfectedFileException('File contains malware and was rejected.');
        }
    }
}
```

---

[← Custom Exporters](./custom-exporters.md) | [Back to Documentation](../INDEX.md) | [Testing →](./testing.md)
