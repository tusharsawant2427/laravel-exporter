# API Reference

Complete API reference for Laravel Exporter classes and methods.

## Exporter Class

Fluent API for building exports.

### Static Methods

#### `make(): static`

Create a new Exporter instance.

```php
$exporter = Exporter::make();
```

### Configuration Methods

#### `format(string $format): static`

Set export format. Supported: `csv`, `xlsx`, `json`

```php
Exporter::make()->format('xlsx');
```

#### `asCsv(): static`

Shortcut for CSV format.

```php
Exporter::make()->asCsv();
```

#### `asExcel(): static`

Shortcut for Excel format.

```php
Exporter::make()->asExcel();
```

#### `asJson(): static`

Shortcut for JSON format.

```php
Exporter::make()->asJson();
```

#### `columns(array|callable $columns): static`

Set columns to export.

```php
// Simple array
Exporter::make()->columns(['id', 'name', 'email']);

// With column definitions
Exporter::make()->columns(fn($cols) => $cols
    ->string('name', 'Name')
    ->amount('total', 'Total')
);
```

#### `defineColumns(callable $callback): static`

Define columns using fluent API.

```php
Exporter::make()->defineColumns(function ($cols) {
    $cols->string('name', 'Name');
    $cols->amount('total', 'Total');
});
```

#### `headers(array $headers): static`

Set custom column headers.

```php
Exporter::make()->headers(['ID', 'Full Name', 'Email']);
```

#### `transformRow(Closure $callback): static`

Transform each row before export.

```php
Exporter::make()->transformRow(function ($row, $item) {
    $row['name'] = strtoupper($row['name']);
    return $row;
});
```

#### `chunkSize(int $size): static`

Set chunk size for processing.

```php
Exporter::make()->chunkSize(500);
```

#### `filename(string $filename): static`

Set default filename.

```php
Exporter::make()->filename('report');
```

#### `options(array $options): static`

Set format-specific options.

```php
Exporter::make()->options([
    'delimiter' => ';',
    'sheet_name' => 'Data',
]);
```

#### `locale(string $locale): static`

Set locale for formatting.

```php
Exporter::make()->locale('en_IN');
```

#### `conditionalColoring(bool $enabled = true): static`

Enable/disable conditional coloring.

```php
Exporter::make()->conditionalColoring(true);
```

#### `from(object|array $source): DataExporter`

Set data source and return DataExporter.

```php
Exporter::make()->from(User::query());
Exporter::make()->from(collect($data));
Exporter::make()->from($array);
```

### Report Header Methods

#### `header(callable $callback): static`

Add report header.

```php
Exporter::make()->header(fn($h) => $h
    ->company('Acme Corp')
    ->title('Report')
);
```

### Totals Methods

#### `withTotals(array $columns): static`

Enable totals for specified columns.

```php
Exporter::make()->withTotals(['qty', 'total']);
```

#### `totalsLabel(string $label): static`

Set totals row label.

```php
Exporter::make()->totalsLabel('GRAND TOTAL');
```

---

## DataExporter Class

Handles actual export operations.

### Output Methods

#### `toFile(string $path): bool`

Export to file.

```php
Exporter::make()
    ->from($data)
    ->toFile('/path/to/file.csv');
```

#### `download(?string $filename = null): mixed`

Return download response.

```php
return Exporter::make()
    ->from($data)
    ->download('export.xlsx');
```

#### `stream(?string $filename = null): mixed`

Stream response.

```php
return Exporter::make()
    ->from($data)
    ->stream('large-export.csv');
```

#### `toString(): string`

Get export as string.

```php
$content = Exporter::make()
    ->from($data)
    ->toString();
```

---

## Excel Class

Maatwebsite-style export/import manager.

### Export Methods

#### `download(object $export, string $filename, ?string $writerType = null): Response`

Download export.

```php
Excel::download(new UsersExport, 'users.xlsx');
```

#### `store(object $export, string $path, ?string $disk = null, ?string $writerType = null): bool`

Store export to disk.

```php
Excel::store(new UsersExport, 'exports/users.xlsx', 'local');
```

#### `raw(object $export, ?string $writerType = null): string`

Get raw export content.

```php
$content = Excel::raw(new UsersExport);
```

### Import Methods

#### `import(object $import, string $filePath, ?string $disk = null, ?string $readerType = null): ImportResult`

Import file.

```php
$result = Excel::import(new UsersImport, 'users.xlsx');
```

#### `toArray(object $import, string $filePath, ?string $disk = null, ?string $readerType = null): array`

Convert file to array.

```php
$rows = Excel::toArray(new UsersImport, 'users.xlsx');
```

#### `toCollection(object $import, string $filePath, ?string $disk = null, ?string $readerType = null): Collection`

Convert file to Collection.

```php
$collection = Excel::toCollection(new UsersImport, 'users.xlsx');
```

---

## ImportResult Class

Import operation result.

### Methods

#### `totalRows(): int`

Get total rows processed.

```php
$result->totalRows();  // 1000
```

#### `importedRows(): int`

Get successfully imported rows.

```php
$result->importedRows();  // 950
```

#### `skippedRows(): int`

Get skipped rows.

```php
$result->skippedRows();  // 30
```

#### `failedRows(): int`

Get failed rows.

```php
$result->failedRows();  // 20
```

#### `successRate(): float`

Get success rate percentage.

```php
$result->successRate();  // 95.0
```

#### `duration(): float`

Get duration in seconds.

```php
$result->duration();  // 12.5
```

#### `peakMemory(): int`

Get peak memory in bytes.

```php
$result->peakMemory();  // 67108864
```

#### `peakMemoryFormatted(): string`

Get formatted peak memory.

```php
$result->peakMemoryFormatted();  // "64 MB"
```

#### `errors(): ImportErrors`

Get errors object.

```php
$result->errors()->hasErrors();
$result->errors()->hasFailures();
```

---

## ColumnCollection Class

Fluent column definition builder.

### Column Type Methods

#### `string(string $key, ?string $label = null): static`

Add string column.

```php
$cols->string('name', 'Full Name');
```

#### `integer(string $key, ?string $label = null): static`

Add integer column.

```php
$cols->integer('quantity', 'Qty');
```

#### `amount(string $key, ?string $label = null): static`

Add amount column with conditional coloring.

```php
$cols->amount('total', 'Total');
```

#### `amountPlain(string $key, ?string $label = null): static`

Add amount column without coloring.

```php
$cols->amountPlain('price', 'Price');
```

#### `percentage(string $key, ?string $label = null): static`

Add percentage column.

```php
$cols->percentage('discount', 'Discount %');
```

#### `date(string $key, ?string $label = null): static`

Add date column.

```php
$cols->date('created_at', 'Created');
```

#### `datetime(string $key, ?string $label = null): static`

Add datetime column.

```php
$cols->datetime('updated_at', 'Updated');
```

#### `boolean(string $key, ?string $label = null): static`

Add boolean column.

```php
$cols->boolean('is_active', 'Active');
```

#### `quantity(string $key, ?string $label = null): static`

Add quantity column.

```php
$cols->quantity('stock', 'In Stock');
```

---

## ColumnDefinition Class

Individual column configuration.

### Methods

#### `make(string $key): static`

Create new column definition.

```php
ColumnDefinition::make('total');
```

#### `label(string $label): static`

Set column label.

```php
ColumnDefinition::make('total')->label('Order Total');
```

#### `width(int $width): static`

Set column width.

```php
ColumnDefinition::make('name')->width(30);
```

#### `align(string $alignment): static`

Set alignment: 'left', 'center', 'right'.

```php
ColumnDefinition::make('id')->align('center');
```

#### `decimals(int $places): static`

Set decimal places.

```php
ColumnDefinition::make('price')->decimals(4);
```

#### `format(string $format): static`

Set number format.

```php
ColumnDefinition::make('phone')->format('(###) ###-####');
```

#### `hidden(): static`

Hide column.

```php
ColumnDefinition::make('internal_id')->hidden();
```

#### `transform(callable $callback): static`

Transform column value.

```php
ColumnDefinition::make('name')->transform(fn($v) => strtoupper($v));
```

#### `when(callable $condition, CellStyle $style): static`

Apply conditional style.

```php
ColumnDefinition::make('balance')
    ->when(fn($v) => $v < 0, CellStyle::make()->fontColor('FF0000'));
```

---

## ReportHeader Class

Report header configuration.

### Methods

#### `make(): static`

Create new report header.

```php
ReportHeader::make();
```

#### `company(string $name): static`

Set company name.

```php
ReportHeader::make()->company('Acme Corporation');
```

#### `title(string $title): static`

Set report title.

```php
ReportHeader::make()->title('Sales Report');
```

#### `subtitle(string $subtitle): static`

Set subtitle.

```php
ReportHeader::make()->subtitle('Q4 2024');
```

#### `dateRange(string $from, string $to): static`

Set date range.

```php
ReportHeader::make()->dateRange('01-Oct-2024', '31-Dec-2024');
```

#### `generatedBy(string $name): static`

Set who generated the report.

```php
ReportHeader::make()->generatedBy('John Doe');
```

#### `generatedAt(?string $format = null): static`

Add generation timestamp.

```php
ReportHeader::make()->generatedAt();
ReportHeader::make()->generatedAt('d-M-Y H:i');
```

#### `addLine(string $line): static`

Add custom line.

```php
ReportHeader::make()->addLine('Region: North America');
```

---

## CellStyle Class

Cell style builder.

### Methods

#### `make(): static`

Create new cell style.

```php
CellStyle::make();
```

#### `fontColor(string $hex): static`

Set font color.

```php
CellStyle::make()->fontColor('FF0000');
```

#### `backgroundColor(string $hex): static`

Set background color.

```php
CellStyle::make()->backgroundColor('FFFF00');
```

#### `bold(): static`

Make text bold.

```php
CellStyle::make()->bold();
```

#### `italic(): static`

Make text italic.

```php
CellStyle::make()->italic();
```

#### `underline(): static`

Add underline.

```php
CellStyle::make()->underline();
```

#### `fontSize(int $size): static`

Set font size.

```php
CellStyle::make()->fontSize(14);
```

#### `align(string $alignment): static`

Set alignment.

```php
CellStyle::make()->align('center');
```

---

## Failure Class

Import validation failure.

### Methods

#### `row(): int`

Get row number.

```php
$failure->row();  // 5
```

#### `attribute(): string`

Get failed attribute/column.

```php
$failure->attribute();  // 'email'
```

#### `errors(): array`

Get error messages.

```php
$failure->errors();  // ['The email must be valid.']
```

#### `values(): array`

Get original row data.

```php
$failure->values();  // ['name' => 'John', 'email' => 'invalid']
```

---

## Row Class

Import row wrapper.

### Methods

#### `getRowNumber(): int`

Get row number.

```php
$row->getRowNumber();  // 5
```

#### `toArray(): array`

Get row data as array.

```php
$row->toArray();  // ['name' => 'John', 'email' => 'john@example.com']
```

#### `get(string $key, $default = null): mixed`

Get specific column value.

```php
$row->get('email');  // 'john@example.com'
```
