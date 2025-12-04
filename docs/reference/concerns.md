# Concerns Reference

Complete reference of all available concerns (interfaces) for exports and imports.

## Export Concerns

### Data Source Concerns

#### `FromCollection`

Use a Laravel Collection as data source.

```php
use LaravelExporter\Concerns\FromCollection;

class UsersExport implements FromCollection
{
    public function collection()
    {
        return User::all();
    }
}
```

#### `FromQuery`

Use an Eloquent Builder (memory efficient with cursor).

```php
use LaravelExporter\Concerns\FromQuery;
use Illuminate\Database\Eloquent\Builder;

class UsersExport implements FromQuery
{
    public function query(): Builder
    {
        return User::query()->orderBy('name');
    }
}
```

#### `FromArray`

Use a PHP array as data source.

```php
use LaravelExporter\Concerns\FromArray;

class DataExport implements FromArray
{
    public function array(): array
    {
        return [
            ['John', 'john@example.com'],
            ['Jane', 'jane@example.com'],
        ];
    }
}
```

#### `FromGenerator`

Use a Generator for custom iteration.

```php
use LaravelExporter\Concerns\FromGenerator;
use Generator;

class LogExport implements FromGenerator
{
    public function generator(): Generator
    {
        foreach (File::lines('log.txt') as $line) {
            yield $this->parseLine($line);
        }
    }
}
```

---

### Header & Mapping Concerns

#### `WithHeadings`

Add column headers to export.

```php
use LaravelExporter\Concerns\WithHeadings;

class UsersExport implements WithHeadings
{
    public function headings(): array
    {
        return ['ID', 'Name', 'Email', 'Created At'];
    }
}
```

#### `WithMapping`

Transform each row before exporting.

```php
use LaravelExporter\Concerns\WithMapping;

class UsersExport implements WithMapping
{
    public function map($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->email,
            $user->created_at->format('Y-m-d'),
        ];
    }
}
```

#### `WithColumnDefinitions`

Define column types for formatting.

```php
use LaravelExporter\Concerns\WithColumnDefinitions;
use LaravelExporter\Support\ColumnCollection;

class OrdersExport implements WithColumnDefinitions
{
    public function columnDefinitions(ColumnCollection $columns): void
    {
        $columns
            ->string('order_number', 'Order #')
            ->amount('total', 'Total')
            ->date('created_at', 'Date');
    }
}
```

---

### Formatting Concerns

#### `WithColumnFormatting`

Apply number/date formats (requires PhpSpreadsheet).

```php
use LaravelExporter\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class FinanceExport implements WithColumnFormatting
{
    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'C' => NumberFormat::FORMAT_PERCENTAGE_00,
            'D' => 'dd-mmm-yyyy',
        ];
    }
}
```

#### `WithColumnWidths`

Set fixed column widths.

```php
use LaravelExporter\Concerns\WithColumnWidths;

class UsersExport implements WithColumnWidths
{
    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 25,
            'C' => 30,
        ];
    }
}
```

#### `WithStyles`

Apply cell styles (requires PhpSpreadsheet).

```php
use LaravelExporter\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StyledExport implements WithStyles
{
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
            'A' => ['font' => ['italic' => true]],
        ];
    }
}
```

#### `ShouldAutoSize`

Auto-size columns to fit content.

```php
use LaravelExporter\Concerns\ShouldAutoSize;

class UsersExport implements ShouldAutoSize
{
    // Columns will auto-size
}
```

#### `WithConditionalColoring`

Enable conditional coloring for amounts.

```php
use LaravelExporter\Concerns\WithConditionalColoring;

class FinanceExport implements WithConditionalColoring
{
    public function conditionalColoring(): bool
    {
        return true;
    }
}
```

#### `WithConditionalFormatting`

Advanced conditional formatting (requires PhpSpreadsheet).

```php
use LaravelExporter\Concerns\WithConditionalFormatting;

class FinanceExport implements WithConditionalFormatting
{
    public function conditionalFormatting(): array
    {
        return [
            'D' => [
                'type' => 'cellValue',
                'operator' => 'greaterThan',
                'value' => 1000,
                'style' => ['fill' => ['color' => ['rgb' => 'C6EFCE']]],
            ],
        ];
    }
}
```

---

### Layout Concerns

#### `WithTitle`

Set worksheet title.

```php
use LaravelExporter\Concerns\WithTitle;

class UsersExport implements WithTitle
{
    public function title(): string
    {
        return 'Users List';
    }
}
```

#### `WithFreezeRow`

Freeze header row.

```php
use LaravelExporter\Concerns\WithFreezeRow;

class UsersExport implements WithFreezeRow
{
    public function freezeRow(): int
    {
        return 1;  // Freeze first row
    }
}
```

#### `WithAutoFilter`

Add filter dropdowns to headers.

```php
use LaravelExporter\Concerns\WithAutoFilter;

class UsersExport implements WithAutoFilter
{
    // Auto-filter added to header row
}
```

#### `WithReportHeader`

Add report header block.

```php
use LaravelExporter\Concerns\WithReportHeader;
use LaravelExporter\Support\ReportHeader;

class SalesExport implements WithReportHeader
{
    public function reportHeader(): ReportHeader
    {
        return ReportHeader::make()
            ->company('Acme Corp')
            ->title('Sales Report')
            ->generatedAt();
    }
}
```

#### `WithTotals`

Add totals row.

```php
use LaravelExporter\Concerns\WithTotals;

class SalesExport implements WithTotals
{
    public function totalColumns(): array
    {
        return ['Quantity', 'Total'];
    }

    public function totalLabel(): string
    {
        return 'GRAND TOTAL';
    }
}
```

---

### Multi-Sheet Concerns

#### `WithMultipleSheets`

Export multiple sheets.

```php
use LaravelExporter\Concerns\WithMultipleSheets;

class WorkbookExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Products' => new ProductsExport(),
            'Orders' => new OrdersExport(),
        ];
    }
}
```

---

### Processing Concerns

#### `WithChunkReading`

Process data in chunks.

```php
use LaravelExporter\Concerns\WithChunkReading;

class LargeExport implements WithChunkReading
{
    public function chunkSize(): int
    {
        return 1000;
    }
}
```

#### `WithEvents`

Hook into export lifecycle.

```php
use LaravelExporter\Concerns\WithEvents;

class UsersExport implements WithEvents
{
    public function registerEvents(): array
    {
        return [
            BeforeExport::class => function() {
                // Before export starts
            },
            AfterSheet::class => function() {
                // After sheet is created
            },
        ];
    }
}
```

---

### Exporter Selection Concerns

#### `UseChunkedWriter`

Use chunked PhpSpreadsheet exporter.

```php
use LaravelExporter\Concerns\UseChunkedWriter;

class LargeExport implements UseChunkedWriter
{
    // Uses memory-efficient chunked writing
}
```

#### `UseStyledOpenSpout`

Use styled OpenSpout exporter.

```php
use LaravelExporter\Concerns\UseStyledOpenSpout;

class StyledExport implements UseStyledOpenSpout
{
    // Uses OpenSpout with styling
}
```

#### `UseHybridExporter`

Use hybrid exporter (OpenSpout + XML post-processing).

```php
use LaravelExporter\Concerns\UseHybridExporter;

class HugeExport implements UseHybridExporter
{
    // Best for 100K+ rows with styling
}
```

---

## Import Concerns

### Data Handling Concerns

#### `ToModel`

Convert each row to an Eloquent model.

```php
use LaravelExporter\Concerns\ToModel;

class UsersImport implements ToModel
{
    public function model(array $row): ?User
    {
        return new User([
            'name' => $row['name'],
            'email' => $row['email'],
        ]);
    }
}
```

#### `ToCollection`

Process all rows as a Collection.

```php
use LaravelExporter\Concerns\ToCollection;
use Illuminate\Support\Collection;

class DataImport implements ToCollection
{
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            // Process row
        }
    }
}
```

#### `ToArray`

Process all rows as an array.

```php
use LaravelExporter\Concerns\ToArray;

class DataImport implements ToArray
{
    public function array(array $rows): void
    {
        // Process all rows
    }
}
```

#### `OnEachRow`

Process each row individually.

```php
use LaravelExporter\Concerns\OnEachRow;
use LaravelExporter\Imports\Row;

class LogImport implements OnEachRow
{
    public function onRow(Row $row): void
    {
        $data = $row->toArray();
        $rowNumber = $row->getRowNumber();
    }
}
```

---

### Row Configuration Concerns

#### `WithHeadingRow`

Use first row as array keys.

```php
use LaravelExporter\Concerns\WithHeadingRow;

class UsersImport implements WithHeadingRow
{
    public function headingRow(): int
    {
        return 1;
    }
}
```

#### `WithStartRow`

Start reading from specific row.

```php
use LaravelExporter\Concerns\WithStartRow;

class DataImport implements WithStartRow
{
    public function startRow(): int
    {
        return 5;  // Start from row 5
    }
}
```

#### `WithLimit`

Limit number of rows.

```php
use LaravelExporter\Concerns\WithLimit;

class PreviewImport implements WithLimit
{
    public function limit(): int
    {
        return 100;
    }
}
```

#### `WithColumnLimit`

Limit columns to read.

```php
use LaravelExporter\Concerns\WithColumnLimit;

class PartialImport implements WithColumnLimit
{
    public function columnLimit(): string
    {
        return 'F';  // Only columns A-F
    }
}
```

---

### Validation Concerns

#### `WithValidation`

Validate each row.

```php
use LaravelExporter\Concerns\WithValidation;

class UsersImport implements WithValidation
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
        ];
    }

    public function customValidationMessages(): array
    {
        return ['email.unique' => 'Email already exists'];
    }

    public function customValidationAttributes(): array
    {
        return ['email' => 'Email Address'];
    }
}
```

---

### Error Handling Concerns

#### `SkipsOnError`

Skip rows that throw exceptions.

```php
use LaravelExporter\Concerns\SkipsOnError;
use Throwable;

class SafeImport implements SkipsOnError
{
    public function onError(Throwable $e): void
    {
        logger()->error($e->getMessage());
    }
}
```

#### `SkipsOnFailure`

Skip rows that fail validation.

```php
use LaravelExporter\Concerns\SkipsOnFailure;
use LaravelExporter\Imports\Failure;

class SafeImport implements SkipsOnFailure
{
    protected array $failures = [];

    public function onFailure(Failure ...$failures): void
    {
        $this->failures = array_merge($this->failures, $failures);
    }
}
```

---

### Batch Processing Concerns

#### `WithBatchInserts`

Insert models in batches.

```php
use LaravelExporter\Concerns\WithBatchInserts;

class BulkImport implements WithBatchInserts
{
    public function batchSize(): int
    {
        return 500;
    }
}
```

#### `WithUpserts`

Update existing or create new.

```php
use LaravelExporter\Concerns\WithUpserts;

class ProductsImport implements WithUpserts
{
    public function uniqueBy(): string|array
    {
        return 'sku';
    }
}
```

#### `WithChunkReading`

Read file in chunks.

```php
use LaravelExporter\Concerns\WithChunkReading;

class LargeImport implements WithChunkReading
{
    public function chunkSize(): int
    {
        return 1000;
    }
}
```

---

### Special Features Concerns

#### `WithCalculatedFormulas`

Get formula results instead of formulas.

```php
use LaravelExporter\Concerns\WithCalculatedFormulas;

class FormulaImport implements WithCalculatedFormulas
{
    // Formulas are calculated before import
}
```

#### `WithMappedCells`

Read specific cells.

```php
use LaravelExporter\Concerns\WithMappedCells;

class ConfigImport implements WithMappedCells
{
    public function mapping(): array
    {
        return [
            'company_name' => 'A1',
            'total' => 'D10',
        ];
    }
}
```

#### `WithMultipleSheets`

Handle multiple sheets.

```php
use LaravelExporter\Concerns\WithMultipleSheets;

class WorkbookImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            0 => new UsersImport(),
            'Products' => new ProductsImport(),
        ];
    }
}
```

#### `WithProgressBar`

Track import progress.

```php
use LaravelExporter\Concerns\WithProgressBar;

class TrackedImport implements WithProgressBar
{
    // Enables progress tracking
}
```

---

### Helper Traits

#### `Exportable`

Add convenience methods to export classes.

```php
use LaravelExporter\Concerns\Exportable;

class UsersExport implements FromQuery
{
    use Exportable;
}

// Usage
(new UsersExport)->download('users.xlsx');
(new UsersExport)->store('path.xlsx', 'disk');
```

#### `Importable`

Add convenience methods to import classes.

```php
use LaravelExporter\Concerns\Importable;

class UsersImport implements ToModel
{
    use Importable;
}

// Usage
(new UsersImport)->import('users.xlsx');
$array = (new UsersImport)->toArray('users.xlsx');
```

#### `RemembersRowNumber`

Track current row number.

```php
use LaravelExporter\Concerns\RemembersRowNumber;

class TrackedImport implements ToModel
{
    use RemembersRowNumber;

    public function model(array $row): Model
    {
        $rowNumber = $this->getRowNumber();
    }
}
```

#### `RemembersChunkOffset`

Track chunk offset.

```php
use LaravelExporter\Concerns\RemembersChunkOffset;

class ChunkedImport implements ToModel, WithChunkReading
{
    use RemembersChunkOffset;

    public function model(array $row): Model
    {
        $offset = $this->getChunkOffset();
    }
}
```
