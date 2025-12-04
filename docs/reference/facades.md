# Facades Reference

Laravel Exporter provides two facades for easy access.

## Exporter Facade

`LaravelExporter\Facades\Exporter`

The main facade for fluent API exports.

### Registration

The facade is auto-registered. Manual registration:

```php
// config/app.php
'aliases' => [
    'Exporter' => LaravelExporter\Facades\Exporter::class,
],
```

### Usage

```php
use LaravelExporter\Facades\Exporter;

// Basic export
Exporter::make()
    ->columns(['id', 'name', 'email'])
    ->from(User::query())
    ->download('users.csv');

// Excel with column types
Exporter::make()
    ->format('xlsx')
    ->columns(fn($cols) => $cols
        ->string('name', 'Name')
        ->amount('total', 'Total')
    )
    ->from(Order::query())
    ->download('orders.xlsx');

// With all options
Exporter::make()
    ->format('xlsx')
    ->locale('en_IN')
    ->conditionalColoring(true)
    ->chunkSize(500)
    ->header(fn($h) => $h
        ->company('Acme Corp')
        ->title('Sales Report')
    )
    ->columns(fn($cols) => $cols
        ->string('id', 'ID')
        ->amount('total', 'Total')
    )
    ->withTotals(['total'])
    ->totalsLabel('GRAND TOTAL')
    ->transformRow(fn($row) => $row)
    ->from(Order::query())
    ->download('report.xlsx');
```

### Available Methods

| Method | Description |
|--------|-------------|
| `make()` | Create new Exporter instance |
| `format(string)` | Set export format |
| `asCsv()` | Shortcut for CSV |
| `asExcel()` | Shortcut for Excel |
| `asJson()` | Shortcut for JSON |
| `columns(array\|callable)` | Set columns |
| `headers(array)` | Set custom headers |
| `transformRow(Closure)` | Transform rows |
| `chunkSize(int)` | Set chunk size |
| `options(array)` | Set format options |
| `locale(string)` | Set locale |
| `conditionalColoring(bool)` | Enable/disable coloring |
| `header(callable)` | Add report header |
| `withTotals(array)` | Enable totals |
| `totalsLabel(string)` | Set totals label |
| `from(object\|array)` | Set data source |

---

## Excel Facade

`LaravelExporter\Facades\Excel`

Maatwebsite-style facade for class-based exports/imports.

### Registration

The facade is auto-registered. Manual registration:

```php
// config/app.php
'aliases' => [
    'Excel' => LaravelExporter\Facades\Excel::class,
],
```

### Export Usage

```php
use LaravelExporter\Facades\Excel;
use App\Exports\UsersExport;

// Download
return Excel::download(new UsersExport, 'users.xlsx');

// Store to disk
Excel::store(new UsersExport, 'exports/users.xlsx', 'local');

// Store to S3
Excel::store(new UsersExport, 'exports/users.xlsx', 's3');

// Get raw content
$content = Excel::raw(new UsersExport, 'xlsx');

// With writer type override
Excel::download(new UsersExport, 'users.xlsx', 'Xlsx');
```

### Import Usage

```php
use LaravelExporter\Facades\Excel;
use App\Imports\UsersImport;

// Basic import
$result = Excel::import(new UsersImport, 'users.xlsx');

// From uploaded file
Excel::import(new UsersImport, $request->file('file'));

// From storage disk
Excel::import(new UsersImport, 'imports/users.xlsx', 's3');

// Convert to array
$rows = Excel::toArray(new UsersImport, 'users.xlsx');

// Convert to collection
$collection = Excel::toCollection(new UsersImport, 'users.xlsx');
```

### Available Methods

| Method | Description |
|--------|-------------|
| `download(export, filename, writerType?)` | Download export |
| `store(export, path, disk?, writerType?)` | Store to disk |
| `raw(export, writerType?)` | Get raw content |
| `import(import, file, disk?, readerType?)` | Import file |
| `toArray(import, file, disk?, readerType?)` | Convert to array |
| `toCollection(import, file, disk?, readerType?)` | Convert to Collection |

---

## Using Without Facades

You can also use the classes directly:

### Exporter Class

```php
use LaravelExporter\Exporter;

$exporter = Exporter::make()
    ->columns(['id', 'name'])
    ->from(User::query());

// Save to file
$exporter->toFile('users.csv');

// Download
return $exporter->download('users.csv');
```

### Excel Class

```php
use LaravelExporter\Excel;

$excel = new Excel();

// Export
$excel->download(new UsersExport, 'users.xlsx');

// Import
$result = $excel->import(new UsersImport, 'users.xlsx');
```

### Importer Class

```php
use LaravelExporter\Importer;

$importer = new Importer();

// Import
$result = $importer->import(new UsersImport, 'users.xlsx');

// Convert to array
$array = $importer->toArray(new UsersImport, 'users.xlsx');
```

---

## Dependency Injection

You can also inject the classes:

### Controller Injection

```php
use LaravelExporter\Excel;

class ExportController extends Controller
{
    public function __construct(
        protected Excel $excel
    ) {}

    public function export()
    {
        return $this->excel->download(new UsersExport, 'users.xlsx');
    }
}
```

### Service Injection

```php
use LaravelExporter\Exporter;

class ReportService
{
    public function generateReport(Query $query): string
    {
        return Exporter::make()
            ->format('xlsx')
            ->from($query)
            ->toString();
    }
}
```

---

## Testing with Facades

### Mocking Exports

```php
use LaravelExporter\Facades\Excel;

Excel::fake();

// Perform export
$this->get('/export/users');

// Assert export was created
Excel::assertDownloaded('users.xlsx', function (UsersExport $export) {
    return $export->query()->count() > 0;
});
```

### Mocking Imports

```php
use LaravelExporter\Facades\Excel;

Excel::fake();

// Perform import
$this->post('/import/users', [
    'file' => $file,
]);

// Assert import was called
Excel::assertImported('users.xlsx');
```
