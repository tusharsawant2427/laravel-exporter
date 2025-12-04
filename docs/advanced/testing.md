# Testing Exports and Imports

This guide covers strategies and best practices for testing your export and import functionality using Pest and PHPUnit.

## Table of Contents

1. [Setting Up the Test Environment](#setting-up-the-test-environment)
2. [Testing Exports](#testing-exports)
3. [Testing Imports](#testing-imports)
4. [Testing with Fake Storage](#testing-with-fake-storage)
5. [Testing Large Datasets](#testing-large-datasets)
6. [Integration Testing](#integration-testing)
7. [Mocking and Stubbing](#mocking-and-stubbing)

---

## Setting Up the Test Environment

### Install Required Packages

```bash
composer require --dev phpoffice/phpspreadsheet
composer require --dev pestphp/pest
```

### Base Test Case Setup

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use fake storage for all tests
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        // Clean up any temp files
        $this->cleanupTempFiles();
        
        parent::tearDown();
    }

    protected function cleanupTempFiles(): void
    {
        $tempPath = storage_path('app/temp');
        if (is_dir($tempPath)) {
            array_map('unlink', glob("$tempPath/*"));
        }
    }
}
```

### Test Helpers Trait

```php
<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

trait ExportTestHelpers
{
    protected function getExportContent(string $path): array
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        
        return match($extension) {
            'csv' => $this->parseCsvFile($path),
            'xlsx' => $this->parseExcelFile($path),
            'json' => json_decode(file_get_contents($path), true),
            default => throw new \InvalidArgumentException("Unsupported format: $extension"),
        };
    }

    protected function parseCsvFile(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        
        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = $data;
        }
        
        fclose($handle);
        return $rows;
    }

    protected function parseExcelFile(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $worksheet = $spreadsheet->getActiveSheet();
        
        return $worksheet->toArray();
    }

    protected function createUploadedExcel(array $data, array $headings = []): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $row = 1;
        
        if (!empty($headings)) {
            foreach ($headings as $col => $heading) {
                $sheet->setCellValueByColumnAndRow($col + 1, $row, $heading);
            }
            $row++;
        }
        
        foreach ($data as $rowData) {
            $col = 1;
            foreach ($rowData as $value) {
                $sheet->setCellValueByColumnAndRow($col++, $row, $value);
            }
            $row++;
        }
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.xlsx';
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempFile);
        
        return new UploadedFile(
            $tempFile,
            'test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    protected function createUploadedCsv(array $data, array $headings = []): UploadedFile
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.csv';
        $handle = fopen($tempFile, 'w');
        
        if (!empty($headings)) {
            fputcsv($handle, $headings);
        }
        
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        
        fclose($handle);
        
        return new UploadedFile(
            $tempFile,
            'test.csv',
            'text/csv',
            null,
            true
        );
    }
}
```

---

## Testing Exports

### Basic Export Test

```php
<?php

use App\Exports\OrdersExport;
use App\Models\Order;
use DataSuite\LaravelExporter\Facades\Excel;
use Illuminate\Support\Facades\Storage;

it('exports orders to xlsx', function () {
    // Arrange
    $orders = Order::factory()->count(5)->create();
    
    // Act
    Excel::store(new OrdersExport(), 'orders.xlsx');
    
    // Assert
    Storage::assertExists('orders.xlsx');
});

it('exports orders with correct headers', function () {
    Order::factory()->count(3)->create();
    
    $path = storage_path('app/test-orders.xlsx');
    Excel::store(new OrdersExport(), $path);
    
    $content = $this->parseExcelFile($path);
    
    expect($content[0])->toBe([
        'Order ID',
        'Customer Name', 
        'Total',
        'Status',
        'Created At',
    ]);
});

it('exports orders with correct data', function () {
    $order = Order::factory()->create([
        'order_number' => 'ORD-001',
        'total' => 150.00,
        'status' => 'completed',
    ]);
    
    $path = storage_path('app/test-orders.xlsx');
    Excel::store(new OrdersExport(), $path);
    
    $content = $this->parseExcelFile($path);
    
    // Skip header row
    $dataRow = $content[1];
    
    expect($dataRow[0])->toBe('ORD-001')
        ->and((float) $dataRow[2])->toBe(150.00)
        ->and($dataRow[3])->toBe('completed');
});
```

### Testing Column Definitions

```php
<?php

use App\Exports\ProductsExport;
use App\Models\Product;
use DataSuite\LaravelExporter\Support\ColumnCollection;

it('has correct column definitions', function () {
    $export = new ProductsExport();
    
    $columns = $export->columnDefinitions();
    
    expect($columns)->toBeInstanceOf(ColumnCollection::class)
        ->and($columns->count())->toBe(5);
    
    $columnArray = $columns->all();
    
    expect($columnArray[0]->name)->toBe('SKU')
        ->and($columnArray[0]->type)->toBe('string')
        ->and($columnArray[2]->name)->toBe('Price')
        ->and($columnArray[2]->type)->toBe('amount');
});
```

### Testing Query-Based Exports

```php
<?php

use App\Exports\OrdersExport;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

it('filters orders by status', function () {
    Order::factory()->count(3)->create(['status' => 'completed']);
    Order::factory()->count(2)->create(['status' => 'pending']);
    
    $export = new OrdersExport(status: 'completed');
    
    $query = $export->query();
    
    expect($query)->toBeInstanceOf(Builder::class)
        ->and($query->count())->toBe(3);
});

it('filters orders by date range', function () {
    Order::factory()->create(['created_at' => now()->subDays(10)]);
    Order::factory()->create(['created_at' => now()->subDays(5)]);
    Order::factory()->create(['created_at' => now()]);
    
    $export = new OrdersExport(
        startDate: now()->subDays(7)->format('Y-m-d'),
        endDate: now()->format('Y-m-d')
    );
    
    expect($export->query()->count())->toBe(2);
});
```

### Testing Report Headers

```php
<?php

use App\Exports\SalesReportExport;
use DataSuite\LaravelExporter\Support\ReportHeader;

it('generates correct report header', function () {
    $export = new SalesReportExport(
        startDate: '2024-01-01',
        endDate: '2024-01-31'
    );
    
    $header = $export->reportHeader();
    
    expect($header)->toBeInstanceOf(ReportHeader::class)
        ->and($header->title)->toBe('Sales Report')
        ->and($header->info)->toHaveKey('Period')
        ->and($header->info['Period'])->toBe('2024-01-01 to 2024-01-31');
});
```

### Testing Totals

```php
<?php

use App\Exports\OrdersExport;
use App\Models\Order;

it('calculates correct totals', function () {
    Order::factory()->create(['total' => 100.00]);
    Order::factory()->create(['total' => 200.00]);
    Order::factory()->create(['total' => 150.00]);
    
    $path = storage_path('app/test-orders.xlsx');
    Excel::store(new OrdersExport(), $path);
    
    $content = $this->parseExcelFile($path);
    $lastRow = end($content);
    
    // Assuming total column is index 2
    expect((float) $lastRow[2])->toBe(450.00);
});
```

### Testing Conditional Coloring

```php
<?php

use App\Exports\OrdersExport;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('applies conditional colors to status column', function () {
    Order::factory()->create(['status' => 'completed']);
    Order::factory()->create(['status' => 'pending']);
    
    $path = storage_path('app/test-orders.xlsx');
    Excel::store(new OrdersExport(), $path);
    
    $spreadsheet = IOFactory::load($path);
    $sheet = $spreadsheet->getActiveSheet();
    
    // Check cell color for completed status (row 2, column D for status)
    $completedCell = $sheet->getCell('D2');
    $fill = $completedCell->getStyle()->getFill();
    
    expect($fill->getStartColor()->getRGB())->toBe('28A745'); // Green
    
    // Check pending status
    $pendingCell = $sheet->getCell('D3');
    $fill = $pendingCell->getStyle()->getFill();
    
    expect($fill->getStartColor()->getRGB())->toBe('FFC107'); // Yellow
});
```

---

## Testing Imports

### Basic Import Test

```php
<?php

use App\Imports\ProductsImport;
use App\Models\Product;
use DataSuite\LaravelExporter\Facades\Excel;

it('imports products from xlsx', function () {
    $file = $this->createUploadedExcel(
        data: [
            ['SKU-001', 'Product 1', 10.00, 100],
            ['SKU-002', 'Product 2', 20.00, 50],
        ],
        headings: ['sku', 'name', 'price', 'quantity']
    );
    
    Excel::import(new ProductsImport(), $file);
    
    expect(Product::count())->toBe(2)
        ->and(Product::where('sku', 'SKU-001')->exists())->toBeTrue();
});

it('imports products from csv', function () {
    $file = $this->createUploadedCsv(
        data: [
            ['SKU-001', 'Product 1', '10.00', '100'],
        ],
        headings: ['sku', 'name', 'price', 'quantity']
    );
    
    Excel::import(new ProductsImport(), $file);
    
    expect(Product::count())->toBe(1);
});
```

### Testing Validation

```php
<?php

use App\Imports\ProductsImport;
use DataSuite\LaravelExporter\Facades\Excel;

it('validates required fields', function () {
    $file = $this->createUploadedExcel(
        data: [
            ['', 'Product Without SKU', 10.00, 100],  // Missing SKU
        ],
        headings: ['sku', 'name', 'price', 'quantity']
    );
    
    $import = new ProductsImport();
    Excel::import($import, $file);
    
    $failures = $import->failures();
    
    expect($failures)->toHaveCount(1)
        ->and($failures[0]->attribute())->toBe('sku')
        ->and($failures[0]->errors())->toContain('SKU is required');
});

it('validates unique sku', function () {
    Product::factory()->create(['sku' => 'EXISTING-SKU']);
    
    $file = $this->createUploadedExcel(
        data: [
            ['EXISTING-SKU', 'Duplicate Product', 10.00, 100],
        ],
        headings: ['sku', 'name', 'price', 'quantity']
    );
    
    $import = new ProductsImport();
    Excel::import($import, $file);
    
    $failures = $import->failures();
    
    expect($failures)->toHaveCount(1)
        ->and($failures[0]->errors())->toContain('SKU already exists');
});

it('validates numeric price', function () {
    $file = $this->createUploadedExcel(
        data: [
            ['SKU-001', 'Product', 'not-a-number', 100],
        ],
        headings: ['sku', 'name', 'price', 'quantity']
    );
    
    $import = new ProductsImport();
    Excel::import($import, $file);
    
    $failures = $import->failures();
    
    expect($failures)->toHaveCount(1)
        ->and($failures[0]->attribute())->toBe('price');
});
```

### Testing Skip on Error

```php
<?php

use App\Imports\ProductsImport;
use App\Models\Product;

it('skips invalid rows and continues import', function () {
    $file = $this->createUploadedExcel(
        data: [
            ['SKU-001', 'Valid Product', 10.00, 100],
            ['', 'Invalid - No SKU', 20.00, 50],  // Will be skipped
            ['SKU-003', 'Another Valid', 30.00, 25],
        ],
        headings: ['sku', 'name', 'price', 'quantity']
    );
    
    $import = new ProductsImport();
    Excel::import($import, $file);
    
    expect(Product::count())->toBe(2)
        ->and($import->failures())->toHaveCount(1);
});
```

### Testing Batch Inserts

```php
<?php

use App\Imports\ProductsImport;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

it('uses batch inserts for efficiency', function () {
    $data = [];
    for ($i = 1; $i <= 100; $i++) {
        $data[] = ["SKU-{$i}", "Product {$i}", 10.00, 100];
    }
    
    $file = $this->createUploadedExcel(
        data: $data,
        headings: ['sku', 'name', 'price', 'quantity']
    );
    
    $queryCount = 0;
    DB::listen(function ($query) use (&$queryCount) {
        if (str_starts_with($query->sql, 'insert')) {
            $queryCount++;
        }
    });
    
    Excel::import(new ProductsImport(), $file);
    
    expect(Product::count())->toBe(100)
        // With batch size of 50, should be 2 insert queries
        ->and($queryCount)->toBeLessThan(5);
});
```

### Testing Import Results

```php
<?php

use App\Imports\ProductsImport;
use DataSuite\LaravelExporter\Facades\Excel;

it('returns import result with statistics', function () {
    $file = $this->createUploadedExcel(
        data: [
            ['SKU-001', 'Product 1', 10.00, 100],
            ['SKU-002', 'Product 2', 20.00, 50],
            ['', 'Invalid', 30.00, 25],  // Will fail
        ],
        headings: ['sku', 'name', 'price', 'quantity']
    );
    
    $import = new ProductsImport();
    $result = Excel::import($import, $file);
    
    expect($result->totalRows())->toBe(3)
        ->and($result->successCount())->toBe(2)
        ->and($result->failureCount())->toBe(1)
        ->and($result->hasFailures())->toBeTrue();
});
```

---

## Testing with Fake Storage

### Store to Fake Disk

```php
<?php

use App\Exports\OrdersExport;
use DataSuite\LaravelExporter\Facades\Excel;
use Illuminate\Support\Facades\Storage;

it('stores export to configured disk', function () {
    Storage::fake('exports');
    
    Order::factory()->count(3)->create();
    
    Excel::store(new OrdersExport(), 'reports/orders.xlsx', 'exports');
    
    Storage::disk('exports')->assertExists('reports/orders.xlsx');
});

it('stores with correct file size', function () {
    Storage::fake('local');
    
    Order::factory()->count(100)->create();
    
    Excel::store(new OrdersExport(), 'orders.xlsx');
    
    $size = Storage::size('orders.xlsx');
    
    expect($size)->toBeGreaterThan(0);
});
```

---

## Testing Large Datasets

### Memory Usage Test

```php
<?php

use App\Exports\LargeExport;
use App\Models\Order;
use DataSuite\LaravelExporter\Facades\Excel;

it('handles large exports within memory limit', function () {
    $memoryBefore = memory_get_usage();
    
    // Create 10000 orders
    Order::factory()->count(10000)->create();
    
    $path = storage_path('app/large-export.xlsx');
    Excel::store(new LargeExport(), $path);
    
    $memoryPeak = memory_get_peak_usage() - $memoryBefore;
    $memoryLimitMB = 128;
    
    expect($memoryPeak)->toBeLessThan($memoryLimitMB * 1024 * 1024);
})->group('performance');

it('chunks large exports correctly', function () {
    Order::factory()->count(5000)->create();
    
    $export = new LargeExport();
    
    expect($export->chunkSize())->toBe(1000);
    
    $processedChunks = 0;
    
    // Mock chunk processing
    $export->query()->chunk($export->chunkSize(), function ($chunk) use (&$processedChunks) {
        $processedChunks++;
        expect($chunk)->toHaveCount(1000);
    });
    
    expect($processedChunks)->toBe(5);
})->group('performance');
```

### Timeout Test

```php
<?php

it('exports within timeout limit', function () {
    Order::factory()->count(50000)->create();
    
    $startTime = microtime(true);
    
    Excel::store(new LargeExport(), 'large.xlsx');
    
    $duration = microtime(true) - $startTime;
    $timeoutSeconds = 120;
    
    expect($duration)->toBeLessThan($timeoutSeconds);
})->group('performance')->skip('Run manually for performance testing');
```

---

## Integration Testing

### HTTP Test for Export Endpoint

```php
<?php

use App\Models\User;
use App\Models\Order;

it('downloads export via http', function () {
    $user = User::factory()->create();
    Order::factory()->count(5)->create();
    
    $response = $this->actingAs($user)
        ->get('/api/orders/export?format=xlsx');
    
    $response->assertStatus(200)
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertDownload('orders.xlsx');
});

it('filters export by query parameters', function () {
    $user = User::factory()->create();
    Order::factory()->count(3)->create(['status' => 'completed']);
    Order::factory()->count(2)->create(['status' => 'pending']);
    
    $response = $this->actingAs($user)
        ->get('/api/orders/export?status=completed');
    
    $response->assertStatus(200);
    
    // Parse response content and verify
    $content = $this->parseExcelContent($response->getContent());
    
    // Header + 3 completed orders
    expect($content)->toHaveCount(4);
});

it('requires authentication for export', function () {
    $response = $this->get('/api/orders/export');
    
    $response->assertStatus(401);
});
```

### HTTP Test for Import Endpoint

```php
<?php

use App\Models\User;
use App\Models\Product;
use Illuminate\Http\UploadedFile;

it('imports file via http', function () {
    $user = User::factory()->create();
    
    $file = $this->createUploadedExcel(
        data: [['SKU-001', 'Product 1', 10.00, 100]],
        headings: ['sku', 'name', 'price', 'quantity']
    );
    
    $response = $this->actingAs($user)
        ->post('/api/products/import', [
            'file' => $file,
        ]);
    
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'imported' => 1,
        ]);
    
    expect(Product::count())->toBe(1);
});

it('returns validation errors on import', function () {
    $user = User::factory()->create();
    
    $file = $this->createUploadedExcel(
        data: [['', 'Missing SKU', 10.00, 100]],
        headings: ['sku', 'name', 'price', 'quantity']
    );
    
    $response = $this->actingAs($user)
        ->post('/api/products/import', [
            'file' => $file,
        ]);
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'imported',
            'failures' => [
                '*' => ['row', 'attribute', 'errors'],
            ],
        ]);
});

it('rejects invalid file types', function () {
    $user = User::factory()->create();
    
    $file = UploadedFile::fake()->create('document.pdf', 100);
    
    $response = $this->actingAs($user)
        ->post('/api/products/import', [
            'file' => $file,
        ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});
```

---

## Mocking and Stubbing

### Mock the Excel Facade

```php
<?php

use DataSuite\LaravelExporter\Facades\Excel;
use Mockery;

it('calls export with correct parameters', function () {
    Excel::shouldReceive('download')
        ->once()
        ->withArgs(function ($export, $filename) {
            return $export instanceof OrdersExport
                && $filename === 'orders.xlsx';
        })
        ->andReturn(response()->download('orders.xlsx'));
    
    $response = $this->get('/api/orders/export');
    
    $response->assertStatus(200);
});

it('handles export failure gracefully', function () {
    Excel::shouldReceive('download')
        ->once()
        ->andThrow(new \Exception('Export failed'));
    
    $response = $this->get('/api/orders/export');
    
    $response->assertStatus(500)
        ->assertJson(['error' => 'Export failed']);
});
```

### Mock Import Results

```php
<?php

use DataSuite\LaravelExporter\ImportResult;

it('processes import result correctly', function () {
    $mockResult = Mockery::mock(ImportResult::class);
    $mockResult->shouldReceive('totalRows')->andReturn(100);
    $mockResult->shouldReceive('successCount')->andReturn(95);
    $mockResult->shouldReceive('failureCount')->andReturn(5);
    $mockResult->shouldReceive('hasFailures')->andReturn(true);
    $mockResult->shouldReceive('failures')->andReturn([
        // Mock failures
    ]);
    
    Excel::shouldReceive('import')
        ->once()
        ->andReturn($mockResult);
    
    // Test your controller logic
});
```

### Partial Mocks

```php
<?php

use App\Exports\OrdersExport;

it('calls query with correct parameters', function () {
    $export = Mockery::mock(OrdersExport::class)->makePartial();
    
    $export->shouldReceive('query')
        ->once()
        ->andReturn(Order::query());
    
    $export->query();
});
```

---

## Test Data Factories

### Excel File Factory

```php
<?php

namespace Tests\Factories;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelFileFactory
{
    protected array $sheets = [];
    protected array $headings = [];

    public static function make(): self
    {
        return new self();
    }

    public function withHeadings(array $headings): self
    {
        $this->headings = $headings;
        return $this;
    }

    public function withRows(array $rows): self
    {
        $this->sheets['Sheet1'] = $rows;
        return $this;
    }

    public function withSheet(string $name, array $rows): self
    {
        $this->sheets[$name] = $rows;
        return $this;
    }

    public function create(): string
    {
        $spreadsheet = new Spreadsheet();
        
        $firstSheet = true;
        foreach ($this->sheets as $sheetName => $rows) {
            if ($firstSheet) {
                $sheet = $spreadsheet->getActiveSheet();
                $firstSheet = false;
            } else {
                $sheet = $spreadsheet->createSheet();
            }
            
            $sheet->setTitle($sheetName);
            
            $row = 1;
            if (!empty($this->headings)) {
                foreach ($this->headings as $col => $heading) {
                    $sheet->setCellValueByColumnAndRow($col + 1, $row, $heading);
                }
                $row++;
            }
            
            foreach ($rows as $rowData) {
                $col = 1;
                foreach ($rowData as $value) {
                    $sheet->setCellValueByColumnAndRow($col++, $row, $value);
                }
                $row++;
            }
        }
        
        $path = tempnam(sys_get_temp_dir(), 'test') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        
        return $path;
    }

    public function upload(): \Illuminate\Http\UploadedFile
    {
        $path = $this->create();
        
        return new \Illuminate\Http\UploadedFile(
            $path,
            'test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
```

### Usage in Tests

```php
<?php

use Tests\Factories\ExcelFileFactory;

it('imports multi-sheet file', function () {
    $file = ExcelFileFactory::make()
        ->withHeadings(['sku', 'name', 'price'])
        ->withSheet('Products', [
            ['SKU-001', 'Product 1', 10.00],
            ['SKU-002', 'Product 2', 20.00],
        ])
        ->withSheet('Categories', [
            ['electronics', 'Electronics'],
            ['clothing', 'Clothing'],
        ])
        ->upload();
    
    Excel::import(new MultiSheetImport(), $file);
    
    expect(Product::count())->toBe(2)
        ->and(Category::count())->toBe(2);
});
```

---

[← Events](./events.md) | [Back to Documentation](../INDEX.md)
