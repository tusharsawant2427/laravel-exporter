# Import Validation

Validate imported data before saving to the database.

## Basic Validation

Implement the `WithValidation` concern:

```php
<?php

namespace App\Imports;

use App\Models\User;
use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Concerns\WithValidation;
use LaravelExporter\Concerns\Importable;

class UsersImport implements ToModel, WithHeadingRow, WithValidation
{
    use Importable;

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

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ];
    }
}
```

## Validation Rules

Standard Laravel validation rules apply:

```php
public function rules(): array
{
    return [
        // Required fields
        'name' => 'required|string|max:255',
        'email' => 'required|email',
        
        // Unique constraint
        'sku' => 'required|unique:products,sku',
        
        // Numeric validation
        'price' => 'required|numeric|min:0',
        'quantity' => 'required|integer|min:0',
        
        // Date validation
        'date' => 'required|date|date_format:Y-m-d',
        
        // In list
        'status' => 'required|in:active,inactive,pending',
        
        // Conditional
        'discount' => 'nullable|numeric|between:0,100',
    ];
}
```

## Custom Validation Messages

```php
public function customValidationMessages(): array
{
    return [
        'name.required' => 'The name field is required.',
        'email.required' => 'Please provide an email address.',
        'email.email' => 'The email must be a valid email address.',
        'email.unique' => 'This email is already registered.',
        'price.numeric' => 'Price must be a number.',
        'price.min' => 'Price cannot be negative.',
    ];
}
```

## Custom Attribute Names

```php
public function customValidationAttributes(): array
{
    return [
        'sku' => 'SKU',
        'qty' => 'Quantity',
        'cat_id' => 'Category',
    ];
}
```

## Handling Validation Failures

### Skip on Failure (Recommended)

Continue importing valid rows, collect failures:

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

    public function headingRow(): int
    {
        return 1;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
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

Usage:

```php
$import = new UsersImport;
$result = Excel::import($import, 'users.xlsx');

// Check for failures
$failures = $import->getFailures();

foreach ($failures as $failure) {
    echo "Row {$failure->row()}: ";
    echo "Attribute: {$failure->attribute()}, ";
    echo "Errors: " . implode(', ', $failure->errors());
    echo "\n";
}

echo "Imported: " . $result->importedRows();
echo "Skipped: " . $result->skippedRows();
```

### Throw Exception on First Failure

Stop import on first validation error:

```php
use LaravelExporter\Imports\ValidationException;

try {
    Excel::import(new UsersImport, 'users.xlsx');
} catch (ValidationException $e) {
    $failures = $e->failures();
    
    foreach ($failures as $failure) {
        echo "Row {$failure->row()}: " . implode(', ', $failure->errors());
    }
}
```

## Failure Object

The `Failure` class provides:

```php
$failure->row();        // Row number (int)
$failure->attribute();  // Column/field name (string)
$failure->errors();     // Array of error messages
$failure->values();     // Original row data (array)
```

## Row-Specific Rules

Apply different rules based on row data:

```php
public function rules(): array
{
    return [
        'email' => 'required|email',
        'type' => 'required|in:individual,company',
        // Company-specific rules handled in prepareForValidation
    ];
}

public function prepareForValidation(array $row): array
{
    // Add computed fields or transform data
    if ($row['type'] === 'company') {
        $row['company_name_required'] = true;
    }
    
    return $row;
}
```

## Validation with Database Lookups

```php
<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Concerns\WithValidation;
use LaravelExporter\Concerns\SkipsOnFailure;
use LaravelExporter\Concerns\Importable;
use Illuminate\Validation\Rule;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable;

    protected array $failures = [];
    protected array $categoryCache = [];

    public function model(array $row): Product
    {
        return new Product([
            'sku' => $row['sku'],
            'name' => $row['name'],
            'category_id' => $this->getCategoryId($row['category']),
            'price' => $row['price'],
        ]);
    }

    public function rules(): array
    {
        return [
            'sku' => 'required|unique:products,sku',
            'name' => 'required|string|max:255',
            'category' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!$this->categoryExists($value)) {
                        $fail("Category '{$value}' does not exist.");
                    }
                },
            ],
            'price' => 'required|numeric|min:0',
        ];
    }

    protected function categoryExists(string $name): bool
    {
        if (!isset($this->categoryCache[$name])) {
            $this->categoryCache[$name] = Category::where('name', $name)->exists();
        }
        return $this->categoryCache[$name];
    }

    protected function getCategoryId(string $name): int
    {
        return Category::where('name', $name)->value('id');
    }
}
```

## Displaying Validation Errors

### In Controller

```php
public function import(Request $request)
{
    $import = new UsersImport;
    $result = Excel::import($import, $request->file('file'));
    
    $failures = $import->getFailures();
    
    if (!empty($failures)) {
        return back()
            ->with('warning', sprintf(
                'Imported %d rows. %d rows had errors.',
                $result->importedRows(),
                count($failures)
            ))
            ->with('failures', $failures);
    }
    
    return back()->with('success', 'All rows imported successfully!');
}
```

### In Blade View

```blade
@if(session('failures'))
<div class="alert alert-warning">
    <h5>Import completed with errors:</h5>
    <ul>
        @foreach(session('failures') as $failure)
        <li>
            Row {{ $failure->row() }}:
            @foreach($failure->errors() as $error)
                {{ $error }}
            @endforeach
        </li>
        @endforeach
    </ul>
</div>
@endif
```

## Validation with Import Result

Use the ImportResult to check validation status:

```php
$result = Excel::import($import, 'users.xlsx');

if ($result->errors()->hasFailures()) {
    foreach ($result->errors()->failures() as $failure) {
        // Process failure
    }
}

echo "Success rate: " . $result->successRate() . "%";
```

## Complete Validation Example

```php
<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\Department;
use LaravelExporter\Concerns\ToModel;
use LaravelExporter\Concerns\WithHeadingRow;
use LaravelExporter\Concerns\WithValidation;
use LaravelExporter\Concerns\SkipsOnFailure;
use LaravelExporter\Imports\Failure;
use LaravelExporter\Concerns\Importable;
use Illuminate\Support\Carbon;

class EmployeesImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable;

    protected array $failures = [];
    protected array $departmentCache;

    public function __construct()
    {
        // Pre-cache departments for performance
        $this->departmentCache = Department::pluck('id', 'name')->toArray();
    }

    public function model(array $row): ?Employee
    {
        $departmentId = $this->departmentCache[$row['department']] ?? null;
        
        if (!$departmentId) {
            return null;
        }

        return new Employee([
            'employee_id' => $row['employee_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'department_id' => $departmentId,
            'hire_date' => Carbon::parse($row['hire_date']),
            'salary' => $this->parseSalary($row['salary']),
            'status' => $row['status'] ?? 'active',
        ]);
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|string|unique:employees,employee_id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:employees,email',
            'department' => [
                'required',
                function ($attr, $value, $fail) {
                    if (!isset($this->departmentCache[$value])) {
                        $fail("Department '{$value}' does not exist.");
                    }
                },
            ],
            'hire_date' => 'required|date',
            'salary' => 'required|numeric|min:0',
            'status' => 'nullable|in:active,inactive,on_leave',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'employee_id.unique' => 'Employee ID :input already exists.',
            'email.unique' => 'Email :input is already registered.',
            'salary.min' => 'Salary cannot be negative.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'employee_id' => 'Employee ID',
            'hire_date' => 'Hire Date',
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

    protected function parseSalary($value): float
    {
        return (float) str_replace(['$', ',', ' '], '', $value);
    }
}
```
