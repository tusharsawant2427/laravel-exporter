# Basic Imports

Import data from CSV, Excel, and JSON files into your Laravel application.

## Quick Start

```php
use LaravelExporter\Facades\Excel;
use App\Imports\UsersImport;

// Import from file
Excel::import(new UsersImport, 'users.xlsx');

// Import from uploaded file
Excel::import(new UsersImport, $request->file('file'));

// Import from storage disk
Excel::import(new UsersImport, 'imports/users.xlsx', 's3');
```

## Creating an Import Class

### Basic ToModel Import

```php
<?php

namespace App\Imports;

use App\Models\User;
use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Concerns\Importable;

class UsersImport implements ToModel, WithHeadingRow
{
    use Importable;

    public function model(array $row): ?User
    {
        return new User([
            'name' => $row['name'],
            'email' => $row['email'],
            'password' => bcrypt($row['password'] ?? 'default123'),
        ]);
    }

    public function headingRow(): int
    {
        return 1;  // First row contains headers
    }
}
```

### ToCollection Import

Process all rows as a collection:

```php
<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use LaravelExporter\Concerns\ToCollection;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Concerns\Importable;

class SalesImport implements ToCollection, WithHeadingRow
{
    use Importable;

    protected array $summary = [];

    public function collection(Collection $rows): void
    {
        $this->summary = [
            'total_rows' => $rows->count(),
            'total_revenue' => $rows->sum('amount'),
            'average_sale' => $rows->avg('amount'),
        ];
        
        // Process rows as needed
        foreach ($rows as $row) {
            // Custom processing
        }
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function getSummary(): array
    {
        return $this->summary;
    }
}

// Usage
$import = new SalesImport;
Excel::import($import, 'sales.xlsx');
$summary = $import->getSummary();
```

### ToArray Import

Process rows as a plain array:

```php
<?php

namespace App\Imports;

use LaravelExporter\Concerns\ToArray;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Concerns\Importable;

class DataImport implements ToArray, WithHeadingRow
{
    use Importable;

    protected array $data = [];

    public function array(array $rows): void
    {
        $this->data = $rows;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
```

### OnEachRow Import

Process each row individually:

```php
<?php

namespace App\Imports;

use LaravelExporter\Concerns\OnEachRow;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Imports\Row;
use LaravelExporter\Concerns\Importable;

class LogImport implements OnEachRow, WithHeadingRow
{
    use Importable;

    public function onRow(Row $row): void
    {
        $rowData = $row->toArray();
        $rowNumber = $row->getRowNumber();
        
        // Process this row
        logger()->info("Processing row {$rowNumber}", $rowData);
    }

    public function headingRow(): int
    {
        return 1;
    }
}
```

## Heading Row

Use column headers as array keys:

```php
use LaravelExporter\Concerns\WithHeadingRow;

class MyImport implements ToModel, WithHeadingRow
{
    public function model(array $row): Model
    {
        // Row data is now keyed by header names:
        // ['name' => 'John', 'email' => 'john@example.com']
        // Instead of:
        // [0 => 'John', 1 => 'john@example.com']
        
        return new User([
            'name' => $row['name'],
            'email' => $row['email'],
        ]);
    }

    public function headingRow(): int
    {
        return 1;  // Header is on row 1
    }
}
```

### Custom Heading Row

If headers are on a different row:

```php
public function headingRow(): int
{
    return 3;  // Headers are on row 3
}
```

## Data Sources

### From File Path

```php
Excel::import(new UsersImport, storage_path('app/users.xlsx'));
Excel::import(new UsersImport, 'users.csv');
```

### From Uploaded File

```php
public function import(Request $request)
{
    $request->validate(['file' => 'required|file|mimes:xlsx,csv,json']);
    
    Excel::import(new UsersImport, $request->file('file'));
    
    return back()->with('success', 'Import completed!');
}
```

### From Storage Disk

```php
// From local storage
Excel::import(new UsersImport, 'imports/users.xlsx', 'local');

// From S3
Excel::import(new UsersImport, 'imports/users.xlsx', 's3');

// From public disk
Excel::import(new UsersImport, 'uploads/users.xlsx', 'public');
```

## Supported File Formats

| Format | Extension | Reader |
|--------|-----------|--------|
| CSV | .csv, .txt | Native (streaming) |
| Excel | .xlsx, .xls | OpenSpout (streaming) |
| JSON | .json | Native |

## Convert to Array/Collection

Without creating models:

```php
// Get as array
$rows = Excel::toArray(new UsersImport, 'users.xlsx');

// Get as Collection
$collection = Excel::toCollection(new UsersImport, 'users.xlsx');
```

## Import Result

Get statistics about the import:

```php
$result = Excel::import(new UsersImport, 'users.xlsx');

echo "Total rows: " . $result->totalRows();
echo "Imported: " . $result->importedRows();
echo "Skipped: " . $result->skippedRows();
echo "Failed: " . $result->failedRows();
echo "Success rate: " . $result->successRate() . "%";
echo "Duration: " . $result->duration() . " seconds";
echo "Peak memory: " . $result->peakMemoryFormatted();
```

## Importable Trait

The `Importable` trait adds convenience methods:

```php
use LaravelExporter\Concerns\Importable;

class UsersImport implements ToModel
{
    use Importable;
    
    // ...
}

// Usage
$import = new UsersImport;

// Import file
$import->import('users.xlsx');

// Convert to array
$array = $import->toArray('users.xlsx');

// Convert to collection
$collection = $import->toCollection('users.xlsx');
```

## Skipping Rows

### Return null to skip

```php
public function model(array $row): ?User
{
    // Skip empty rows
    if (empty($row['email'])) {
        return null;
    }
    
    // Skip inactive users
    if ($row['status'] === 'inactive') {
        return null;
    }
    
    return new User([
        'name' => $row['name'],
        'email' => $row['email'],
    ]);
}
```

## Start Row & Limits

### Start from specific row

```php
use LaravelExporter\Concerns\WithStartRow;

class MyImport implements ToModel, WithStartRow
{
    public function startRow(): int
    {
        return 5;  // Start from row 5
    }
}
```

### Limit rows

```php
use LaravelExporter\Concerns\WithLimit;

class MyImport implements ToModel, WithLimit
{
    public function limit(): int
    {
        return 100;  // Only import first 100 rows
    }
}
```

### Limit columns

```php
use LaravelExporter\Concerns\WithColumnLimit;

class MyImport implements ToModel, WithColumnLimit
{
    public function columnLimit(): string
    {
        return 'F';  // Only read columns A through F
    }
}
```

## Complete Example

```php
<?php

namespace App\Imports;

use App\Models\Product;
use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Concerns\WithStartRow;
use LaravelExporter\Concerns\Importable;

class ProductsImport implements ToModel, WithHeadingRow, WithStartRow
{
    use Importable;

    protected int $imported = 0;

    public function model(array $row): ?Product
    {
        // Skip if required fields are missing
        if (empty($row['sku']) || empty($row['name'])) {
            return null;
        }

        $this->imported++;

        return new Product([
            'sku' => $row['sku'],
            'name' => $row['name'],
            'description' => $row['description'] ?? null,
            'price' => (float) str_replace(['$', ','], '', $row['price'] ?? 0),
            'stock' => (int) ($row['stock'] ?? 0),
            'category' => $row['category'] ?? 'Uncategorized',
        ]);
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function startRow(): int
    {
        return 2;  // Skip header
    }

    public function getImportedCount(): int
    {
        return $this->imported;
    }
}

// Usage
$import = new ProductsImport;
$result = Excel::import($import, 'products.xlsx');

echo "Imported: " . $import->getImportedCount();
echo "Duration: " . $result->duration() . "s";
```

## Controller Example

```php
<?php

namespace App\Http\Controllers;

use App\Imports\ProductsImport;
use Illuminate\Http\Request;
use LaravelExporter\Facades\Excel;

class ImportController extends Controller
{
    public function showForm()
    {
        return view('imports.form');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv|max:10240',
        ]);

        try {
            $import = new ProductsImport;
            $result = Excel::import($import, $request->file('file'));

            return back()->with('success', sprintf(
                'Imported %d of %d rows in %s seconds.',
                $result->importedRows(),
                $result->totalRows(),
                number_format($result->duration(), 2)
            ));
        } catch (\Exception $e) {
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}
```
