# Quick Start Guide

Get up and running with Laravel Exporter in under 5 minutes.

## Basic Export (3 Lines of Code)

```php
use LaravelExporter\Facades\Exporter;

// Export all users to CSV
Exporter::make()
    ->from(User::query())
    ->download('users.csv');
```

That's it! This exports all user data to a downloadable CSV file.

## Step-by-Step Examples

### 1. Export to File

Save export directly to the filesystem:

```php
use LaravelExporter\Facades\Exporter;

Exporter::make()
    ->columns(['id', 'name', 'email', 'created_at'])
    ->from(User::query())
    ->toFile(storage_path('app/exports/users.csv'));
```

### 2. Download Response

Return a download response from a controller:

```php
// In your controller
public function exportUsers()
{
    return Exporter::make()
        ->format('xlsx')
        ->columns(['id', 'name', 'email'])
        ->from(User::query())
        ->download('users.xlsx');
}
```

### 3. Export with Custom Headers

Add custom column headers:

```php
Exporter::make()
    ->columns(['id', 'name', 'email'])
    ->headers(['User ID', 'Full Name', 'Email Address'])
    ->from(User::query())
    ->download('users.csv');
```

### 4. Export to Different Formats

```php
// CSV (default)
Exporter::make()->from($data)->download('data.csv');

// Excel
Exporter::make()->format('xlsx')->from($data)->download('data.xlsx');

// JSON
Exporter::make()->format('json')->from($data)->download('data.json');
```

### 5. Export with Column Types (Excel)

Define column types for proper formatting:

```php
Exporter::make()
    ->format('xlsx')
    ->columns(fn($cols) => $cols
        ->string('name', 'Customer Name')
        ->amount('total', 'Order Total')     // Formatted as currency
        ->date('order_date', 'Order Date')   // Formatted as date
        ->quantity('items', 'Item Count')    // Formatted as number
    )
    ->from(Order::query())
    ->download('orders.xlsx');
```

### 6. Transform Data Before Export

Apply transformations to each row:

```php
Exporter::make()
    ->columns(['id', 'name', 'email', 'status'])
    ->transformRow(function (array $row, $model) {
        $row['name'] = strtoupper($row['name']);
        $row['status'] = $model->is_active ? 'Active' : 'Inactive';
        return $row;
    })
    ->from(User::query())
    ->download('users.csv');
```

## Using the Maatwebsite-Style API

If you prefer class-based exports (like Maatwebsite Excel):

### Create an Export Class

```php
<?php

namespace App\Exports;

use App\Models\User;
use LaravelExporter\Concerns\FromQuery;
use LaravelExporter\Concerns\WithHeadings;
use LaravelExporter\Concerns\Exportable;

class UsersExport implements FromQuery, WithHeadings
{
    use Exportable;

    public function query()
    {
        return User::query();
    }

    public function headings(): array
    {
        return ['ID', 'Name', 'Email', 'Created At'];
    }
}
```

### Use the Export Class

```php
use LaravelExporter\Facades\Excel;
use App\Exports\UsersExport;

// Download
return Excel::download(new UsersExport, 'users.xlsx');

// Store to disk
Excel::store(new UsersExport, 'exports/users.xlsx', 'local');
```

## Importing Data

Import data from files just as easily:

```php
use LaravelExporter\Facades\Excel;
use App\Imports\UsersImport;

// Basic import
Excel::import(new UsersImport, 'users.xlsx');

// Import from uploaded file
Excel::import(new UsersImport, $request->file('file'));
```

### Create an Import Class

```php
<?php

namespace App\Imports;

use App\Models\User;
use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\WithHeadingRow;

class UsersImport implements ToModel, WithHeadingRow
{
    public function model(array $row): User
    {
        return new User([
            'name' => $row['name'],
            'email' => $row['email'],
            'password' => bcrypt($row['password']),
        ]);
    }

    public function headingRow(): int
    {
        return 1;
    }
}
```

## Complete Controller Example

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use LaravelExporter\Facades\Exporter;

class ExportController extends Controller
{
    public function export(Request $request)
    {
        $format = $request->get('format', 'csv');
        
        return Exporter::make()
            ->format($format)
            ->columns(['id', 'name', 'email', 'created_at'])
            ->headers(['ID', 'Name', 'Email', 'Registered'])
            ->from(User::query())
            ->download("users.{$format}");
    }
    
    public function exportFiltered(Request $request)
    {
        $query = User::query();
        
        if ($request->has('active')) {
            $query->where('is_active', true);
        }
        
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        
        return Exporter::make()
            ->format('xlsx')
            ->columns(fn($cols) => $cols
                ->string('id', 'User ID')
                ->string('name', 'Full Name')
                ->string('email', 'Email')
                ->string('role', 'Role')
                ->boolean('is_active', 'Active')
                ->date('created_at', 'Registered On')
            )
            ->from($query)
            ->download('filtered-users.xlsx');
    }
}
```

## Routes

```php
// routes/web.php
Route::get('/export/users', [ExportController::class, 'export']);
Route::get('/export/users/filtered', [ExportController::class, 'exportFiltered']);
```

## What's Next?

Now that you have the basics, explore more features:

- [Column Definitions](./exports/column-definitions.md) - Learn about all column types
- [Styling & Formatting](./exports/styling.md) - Add headers, colors, and styles
- [Large Datasets](./exports/large-datasets.md) - Handle 100K+ rows efficiently
- [Importing Data](./imports/basic.md) - Learn about data imports
- [Class-Based Exports](./exports/class-based.md) - Reusable export classes
