# Import Error Handling

Handle import errors gracefully to ensure robust data processing.

## Error Types

1. **Validation Errors** - Data doesn't meet validation rules
2. **Database Errors** - Constraint violations, connection issues
3. **File Errors** - Missing file, wrong format, corrupt file
4. **Runtime Errors** - Memory, timeout, code exceptions

## Skipping Errors

Continue importing despite errors:

### SkipsOnError

Skip rows that throw exceptions:

```php
<?php

namespace App\Imports;

use App\Models\Product;
use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Concerns\SkipsOnError;
use LaravelExporter\Concerns\Importable;
use Throwable;

class ProductsImport implements ToModel, WithHeadingRow, SkipsOnError
{
    use Importable;

    protected array $errors = [];

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

    public function onError(Throwable $e): void
    {
        $this->errors[] = $e->getMessage();
        
        // Log the error
        logger()->error('Import error: ' . $e->getMessage());
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
```

### SkipsOnFailure

Skip rows that fail validation:

```php
<?php

namespace App\Imports;

use App\Models\User;
use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Concerns\WithValidation;
use LaravelExporter\Concerns\SkipsOnFailure;
use LaravelExporter\Imports\Failure;
use LaravelExporter\Concerns\Importable;

class UsersImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable;

    protected array $failures = [];

    public function model(array $row): User
    {
        return new User([
            'name' => $row['name'],
            'email' => $row['email'],
            'password' => bcrypt('password'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
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
}
```

## Collecting All Errors

Create a comprehensive error collector:

```php
<?php

namespace App\Imports;

use App\Models\Product;
use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Concerns\WithValidation;
use LaravelExporter\Concerns\SkipsOnError;
use LaravelExporter\Concerns\SkipsOnFailure;
use LaravelExporter\Imports\Failure;
use LaravelExporter\Concerns\Importable;
use Throwable;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure
{
    use Importable;

    protected array $errors = [];
    protected array $failures = [];
    protected int $rowNumber = 1;

    public function model(array $row): Product
    {
        $this->rowNumber++;
        
        return new Product([
            'sku' => $row['sku'],
            'name' => $row['name'],
            'price' => $row['price'],
        ]);
    }

    public function rules(): array
    {
        return [
            'sku' => 'required|unique:products,sku',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ];
    }

    public function onError(Throwable $e): void
    {
        $this->errors[] = [
            'row' => $this->rowNumber,
            'type' => 'error',
            'message' => $e->getMessage(),
        ];
    }

    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            $this->failures[] = [
                'row' => $failure->row(),
                'type' => 'validation',
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
            ];
        }
    }

    public function getAllIssues(): array
    {
        return [
            'errors' => $this->errors,
            'failures' => $this->failures,
            'total_issues' => count($this->errors) + count($this->failures),
        ];
    }
}
```

## Try-Catch Pattern

Wrap imports in try-catch for file-level errors:

```php
use LaravelExporter\Facades\Excel;
use LaravelExporter\Imports\ValidationException;

public function import(Request $request)
{
    $request->validate([
        'file' => 'required|file|mimes:xlsx,csv,json|max:10240',
    ]);

    try {
        $import = new ProductsImport;
        $result = Excel::import($import, $request->file('file'));
        
        $issues = $import->getAllIssues();
        
        if ($issues['total_issues'] > 0) {
            return back()
                ->with('warning', "Import completed with {$issues['total_issues']} issues")
                ->with('issues', $issues);
        }
        
        return back()->with('success', sprintf(
            'Successfully imported %d rows.',
            $result->importedRows()
        ));
        
    } catch (ValidationException $e) {
        return back()
            ->with('error', 'Validation failed')
            ->with('failures', $e->failures());
            
    } catch (\Exception $e) {
        logger()->error('Import failed', ['error' => $e->getMessage()]);
        
        return back()->with('error', 'Import failed: ' . $e->getMessage());
    }
}
```

## Import Result Errors

Access errors through ImportResult:

```php
$result = Excel::import($import, 'file.xlsx');

// Check for errors
if ($result->errors()->hasErrors()) {
    foreach ($result->errors()->errors() as $rowNumber => $error) {
        echo "Row {$rowNumber}: {$error->getMessage()}";
    }
}

// Check for validation failures
if ($result->errors()->hasFailures()) {
    foreach ($result->errors()->failures() as $failure) {
        echo "Row {$failure->row()}: " . implode(', ', $failure->errors());
    }
}

// Get counts
echo "Failed rows: " . $result->failedRows();
echo "Skipped rows: " . $result->skippedRows();
echo "Success rate: " . $result->successRate() . "%";
```

## Error Reporting

### Log All Errors

```php
public function onError(Throwable $e): void
{
    logger()->error('Import row error', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}

public function onFailure(Failure ...$failures): void
{
    foreach ($failures as $failure) {
        logger()->warning('Import validation failure', [
            'row' => $failure->row(),
            'attribute' => $failure->attribute(),
            'errors' => $failure->errors(),
            'values' => $failure->values(),
        ]);
    }
}
```

### Store Error Report

```php
<?php

namespace App\Imports;

use App\Models\ImportLog;
use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\SkipsOnError;
use LaravelExporter\Concerns\SkipsOnFailure;
use LaravelExporter\Imports\Failure;
use Throwable;

class LoggingImport implements ToModel, SkipsOnError, SkipsOnFailure
{
    protected ImportLog $log;

    public function __construct(ImportLog $log)
    {
        $this->log = $log;
    }

    public function onError(Throwable $e): void
    {
        $this->log->addError($e->getMessage());
    }

    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            $this->log->addFailure([
                'row' => $failure->row(),
                'errors' => $failure->errors(),
            ]);
        }
    }
}
```

## Generate Error Report File

Create a downloadable error report:

```php
public function import(Request $request)
{
    $import = new ProductsImport;
    $result = Excel::import($import, $request->file('file'));
    
    $issues = $import->getAllIssues();
    
    if ($issues['total_issues'] > 0) {
        // Generate error report
        $errorReport = $this->generateErrorReport($issues);
        
        // Store error report
        $filename = 'import-errors-' . now()->format('Y-m-d-His') . '.csv';
        Storage::put("imports/errors/{$filename}", $errorReport);
        
        return back()
            ->with('warning', 'Import completed with errors')
            ->with('error_report', $filename);
    }
    
    return back()->with('success', 'Import completed successfully');
}

protected function generateErrorReport(array $issues): string
{
    $csv = "Row,Type,Field,Error\n";
    
    foreach ($issues['failures'] as $failure) {
        foreach ($failure['errors'] as $error) {
            $csv .= sprintf(
                "%d,%s,%s,\"%s\"\n",
                $failure['row'],
                'Validation',
                $failure['attribute'],
                str_replace('"', '""', $error)
            );
        }
    }
    
    foreach ($issues['errors'] as $error) {
        $csv .= sprintf(
            "%d,%s,-,\"%s\"\n",
            $error['row'],
            'Error',
            str_replace('"', '""', $error['message'])
        );
    }
    
    return $csv;
}
```

## Retry Failed Rows

Save failed rows for retry:

```php
<?php

namespace App\Imports;

use App\Models\FailedImportRow;
use LaravelExporter\Concerns\SkipsOnError;
use LaravelExporter\Concerns\SkipsOnFailure;
use LaravelExporter\Imports\Failure;
use Throwable;

class RetryableImport implements ToModel, SkipsOnError, SkipsOnFailure
{
    protected string $batchId;

    public function __construct()
    {
        $this->batchId = uniqid('import_');
    }

    public function onError(Throwable $e): void
    {
        // We don't have row data in onError
        // Consider using RemembersRowNumber trait
    }

    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            FailedImportRow::create([
                'batch_id' => $this->batchId,
                'row_number' => $failure->row(),
                'row_data' => $failure->values(),
                'errors' => $failure->errors(),
            ]);
        }
    }

    public function getBatchId(): string
    {
        return $this->batchId;
    }
}

// Later, retry failed rows
$failedRows = FailedImportRow::where('batch_id', $batchId)->get();

foreach ($failedRows as $row) {
    try {
        // Attempt to process again
        $this->processRow($row->row_data);
        $row->delete();
    } catch (\Exception $e) {
        $row->increment('retry_count');
        $row->update(['last_error' => $e->getMessage()]);
    }
}
```

## Complete Error Handling Example

```php
<?php

namespace App\Http\Controllers;

use App\Imports\ProductsImport;
use App\Models\ImportLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use LaravelExporter\Facades\Excel;
use LaravelExporter\Imports\ValidationException;

class ImportController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv|max:20480',
        ]);

        $file = $request->file('file');
        
        // Create import log
        $log = ImportLog::create([
            'user_id' => auth()->id(),
            'filename' => $file->getClientOriginalName(),
            'status' => 'processing',
        ]);

        try {
            $import = new ProductsImport($log);
            $result = Excel::import($import, $file);
            
            $issues = $import->getAllIssues();
            
            // Update log
            $log->update([
                'status' => $issues['total_issues'] > 0 ? 'completed_with_errors' : 'completed',
                'total_rows' => $result->totalRows(),
                'imported_rows' => $result->importedRows(),
                'failed_rows' => $result->failedRows(),
                'duration' => $result->duration(),
            ]);
            
            if ($issues['total_issues'] > 0) {
                // Generate and store error report
                $errorFile = $this->storeErrorReport($log, $issues);
                
                return response()->json([
                    'success' => true,
                    'message' => sprintf(
                        'Imported %d rows with %d errors',
                        $result->importedRows(),
                        $issues['total_issues']
                    ),
                    'error_report' => $errorFile,
                    'stats' => [
                        'total' => $result->totalRows(),
                        'imported' => $result->importedRows(),
                        'failed' => $result->failedRows(),
                    ],
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => sprintf('Successfully imported %d rows', $result->importedRows()),
            ]);
            
        } catch (ValidationException $e) {
            $log->update(['status' => 'failed', 'error' => 'Validation failed']);
            
            return response()->json([
                'success' => false,
                'message' => 'Import validation failed',
                'failures' => collect($e->failures())->map(fn($f) => [
                    'row' => $f->row(),
                    'errors' => $f->errors(),
                ])->toArray(),
            ], 422);
            
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            
            logger()->error('Import failed', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    protected function storeErrorReport(ImportLog $log, array $issues): string
    {
        $content = "Row,Type,Field,Error,Original Value\n";
        
        foreach ($issues['failures'] as $failure) {
            foreach ($failure['errors'] as $error) {
                $content .= sprintf(
                    "%d,Validation,%s,\"%s\",\"%s\"\n",
                    $failure['row'],
                    $failure['attribute'],
                    $error,
                    $failure['values'][$failure['attribute']] ?? ''
                );
            }
        }
        
        foreach ($issues['errors'] as $error) {
            $content .= sprintf("%d,Error,-,\"%s\",-\n", $error['row'], $error['message']);
        }
        
        $filename = "import-errors/{$log->id}.csv";
        Storage::put($filename, $content);
        
        return $filename;
    }
}
```
