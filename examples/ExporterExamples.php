<?php

namespace LaravelExporter\Examples;

use LaravelExporter\Exporter;
use LaravelExporter\Support\Sheet;
use LaravelExporter\Support\ReportHeader;
use LaravelExporter\Support\ColumnDefinition;

/**
 * Comprehensive Examples for Laravel Exporter
 *
 * This file demonstrates all features and formats available in the package.
 * Copy any example to your controller or command to use.
 */
class ExporterExamples
{
    // =========================================================================
    // SECTION 1: BASIC EXPORTS (CSV, Excel, JSON)
    // =========================================================================

    /**
     * Example 1.1: Simple CSV Export
     */
    public function basicCsvExport()
    {
        $data = [
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ['id' => 3, 'name' => 'Bob Wilson', 'email' => 'bob@example.com'],
        ];

        return Exporter::make()
            ->format('csv')
            ->columns(['id', 'name', 'email'])
            ->headers(['ID', 'Full Name', 'Email Address'])
            ->from($data)
            ->download('users.csv');
    }

    /**
     * Example 1.2: CSV with Custom Options
     */
    public function csvWithOptions()
    {
        $data = $this->getSampleUsers();

        return Exporter::make()
            ->format('csv')
            ->options([
                'delimiter' => ';',           // Use semicolon instead of comma
                'enclosure' => '"',
                'add_bom' => true,            // Add BOM for Excel compatibility
                'include_headers' => true,
            ])
            ->columns(['id', 'name', 'email', 'created_at'])
            ->from($data)
            ->download('users-semicolon.csv');
    }

    /**
     * Example 1.3: Simple Excel Export
     */
    public function basicExcelExport()
    {
        $data = $this->getSampleProducts();

        return Exporter::make()
            ->format('xlsx')
            ->columns(['code', 'name', 'price', 'stock'])
            ->headers(['Product Code', 'Product Name', 'Price', 'Stock Qty'])
            ->from($data)
            ->download('products.xlsx');
    }

    /**
     * Example 1.4: Simple JSON Export
     */
    public function basicJsonExport()
    {
        $data = $this->getSampleUsers();

        return Exporter::make()
            ->format('json')
            ->columns(['id', 'name', 'email'])
            ->from($data)
            ->download('users.json');
    }

    /**
     * Example 1.5: JSON with Options
     */
    public function jsonWithOptions()
    {
        $data = $this->getSampleUsers();

        return Exporter::make()
            ->format('json')
            ->options([
                'pretty_print' => true,
                'wrap_in_object' => true,
                'data_key' => 'users',
                'include_metadata' => true,
            ])
            ->columns(['id', 'name', 'email'])
            ->from($data)
            ->download('users-formatted.json');
    }

    // =========================================================================
    // SECTION 2: COLUMN TYPES (Fluent Column Definition)
    // =========================================================================

    /**
     * Example 2.1: All Column Types
     */
    public function allColumnTypes()
    {
        $data = [
            [
                'order_no' => 'ORD-001',
                'order_date' => '2024-03-15',
                'customer' => 'ABC Corp',
                'items' => 5,
                'subtotal' => 45000.00,
                'discount' => 10.5,
                'tax' => 8100.00,
                'total' => 48600.00,
                'is_paid' => true,
                'created_at' => '2024-03-15 10:30:45',
            ],
            [
                'order_no' => 'ORD-002',
                'order_date' => '2024-03-16',
                'customer' => 'XYZ Ltd',
                'items' => 3,
                'subtotal' => -12000.00,  // Negative for demo
                'discount' => 5.0,
                'tax' => 2160.00,
                'total' => -10800.00,
                'is_paid' => false,
                'created_at' => '2024-03-16 14:20:30',
            ],
        ];

        return Exporter::make()
            ->format('xlsx')
            ->columns(fn($cols) => $cols
                ->string('order_no', 'Order #')           // Plain text
                ->date('order_date', 'Order Date')        // Date format
                ->string('customer', 'Customer')
                ->integer('items', 'Items Count')         // Whole number
                ->amount('subtotal', 'Subtotal')          // Currency with coloring
                ->percentage('discount', 'Discount %')    // Percentage format
                ->amountPlain('tax', 'Tax Amount')        // Currency without coloring
                ->amount('total', 'Total')                // Green if +ve, Red if -ve
                ->boolean('is_paid', 'Paid?')             // Yes/No
                ->datetime('created_at', 'Created At')    // Date & time
            )
            ->from($data)
            ->download('all-column-types.xlsx');
    }

    /**
     * Example 2.2: Custom Column Definition
     */
    public function customColumnDefinition()
    {
        $data = $this->getSampleProducts();

        return Exporter::make()
            ->format('xlsx')
            ->columns(fn($cols) => $cols
                ->add(
                    ColumnDefinition::make('code')
                        ->label('Product Code')
                        ->string()
                        ->width(15)
                        ->alignCenter()
                )
                ->add(
                    ColumnDefinition::make('name')
                        ->label('Product Name')
                        ->string()
                        ->width(30)
                )
                ->add(
                    ColumnDefinition::make('price')
                        ->label('Unit Price (₹)')
                        ->amount()
                        ->colored(true)
                        ->width(15)
                        ->alignRight()
                )
                ->add(
                    ColumnDefinition::make('stock')
                        ->label('Stock Qty')
                        ->quantity()
                        ->width(12)
                )
            )
            ->from($data)
            ->download('custom-columns.xlsx');
    }

    // =========================================================================
    // SECTION 3: INR LOCALE & CONDITIONAL COLORING
    // =========================================================================

    /**
     * Example 3.1: INR Formatting with Indian Numbering System
     */
    public function inrFormatting()
    {
        $data = [
            ['account' => 'Sales', 'debit' => 0, 'credit' => 1234567.89, 'balance' => 1234567.89],
            ['account' => 'Purchases', 'debit' => 987654.32, 'credit' => 0, 'balance' => -987654.32],
            ['account' => 'Expenses', 'debit' => 45678.90, 'credit' => 0, 'balance' => -45678.90],
            ['account' => 'Income', 'debit' => 0, 'credit' => 234567.00, 'balance' => 234567.00],
        ];

        return Exporter::make()
            ->format('xlsx')
            ->locale('en_IN')  // Indian numbering: 12,34,567.89
            ->conditionalColoring(true)
            ->columns(fn($cols) => $cols
                ->string('account', 'Account Name')
                ->amount('debit', 'Debit (₹)')
                ->amount('credit', 'Credit (₹)')
                ->amount('balance', 'Balance (₹)')  // Green for +ve, Red for -ve
            )
            ->from($data)
            ->download('ledger-inr.xlsx');
    }

    /**
     * Example 3.2: Conditional Coloring for Financial Data
     */
    public function conditionalColoringExample()
    {
        $data = [
            ['month' => 'January', 'revenue' => 150000, 'expenses' => 120000, 'profit' => 30000],
            ['month' => 'February', 'revenue' => 140000, 'expenses' => 155000, 'profit' => -15000],
            ['month' => 'March', 'revenue' => 180000, 'expenses' => 130000, 'profit' => 50000],
            ['month' => 'April', 'revenue' => 120000, 'expenses' => 140000, 'profit' => -20000],
        ];

        return Exporter::make()
            ->format('xlsx')
            ->locale('en_IN')
            ->columns(fn($cols) => $cols
                ->string('month', 'Month')
                ->amountPlain('revenue', 'Revenue')     // No coloring
                ->amountPlain('expenses', 'Expenses')   // No coloring
                ->amount('profit', 'Profit/Loss')       // Colored: Green/Red
            )
            ->from($data)
            ->download('profit-loss.xlsx');
    }

    // =========================================================================
    // SECTION 4: REPORT HEADERS
    // =========================================================================

    /**
     * Example 4.1: Full Report Header
     */
    public function fullReportHeader()
    {
        $data = $this->getSampleSales();

        return Exporter::make()
            ->format('xlsx')
            ->header(fn($h) => $h
                ->company('Acme Corporation Pvt. Ltd.')
                ->title('Monthly Sales Report')
                ->subtitle('All Branches Combined')
                ->dateRange('01-Apr-2024', '30-Apr-2024')
                ->generatedBy('John Admin')
                ->generatedAt()
            )
            ->columns(fn($cols) => $cols
                ->string('invoice_no', 'Invoice #')
                ->date('invoice_date', 'Date')
                ->string('customer', 'Customer')
                ->amount('amount', 'Amount')
            )
            ->from($data)
            ->download('sales-report.xlsx');
    }

    /**
     * Example 4.2: Report Header with ReportHeader Class
     */
    public function reportHeaderObject()
    {
        $data = $this->getSampleSales();

        $header = ReportHeader::make()
            ->company('XYZ Industries')
            ->title('Stock Valuation Report')
            ->asOnDate('31-Mar-2024')
            ->addRow('Warehouse: Main Godown')
            ->addRow('Category: All Items')
            ->generatedAt(new \DateTime());

        return Exporter::make()
            ->format('xlsx')
            ->header($header)
            ->columns(fn($cols) => $cols
                ->string('code', 'Item Code')
                ->string('name', 'Description')
                ->quantity('qty', 'Quantity')
                ->amount('rate', 'Rate')
                ->amount('value', 'Value')
            )
            ->from($data)
            ->download('stock-valuation.xlsx');
    }

    // =========================================================================
    // SECTION 5: TOTALS ROW
    // =========================================================================

    /**
     * Example 5.1: Automatic Totals
     */
    public function automaticTotals()
    {
        $data = [
            ['product' => 'Widget A', 'qty' => 100, 'rate' => 50.00, 'amount' => 5000.00],
            ['product' => 'Widget B', 'qty' => 75, 'rate' => 80.00, 'amount' => 6000.00],
            ['product' => 'Widget C', 'qty' => 50, 'rate' => 120.00, 'amount' => 6000.00],
            ['product' => 'Widget D', 'qty' => 200, 'rate' => 25.00, 'amount' => 5000.00],
        ];

        return Exporter::make()
            ->format('xlsx')
            ->columns(fn($cols) => $cols
                ->string('product', 'Product')
                ->quantity('qty', 'Quantity')
                ->amount('rate', 'Rate')
                ->amount('amount', 'Amount')
            )
            ->withTotals(['qty', 'amount'])  // Only sum these columns
            ->totalsLabel('GRAND TOTAL')
            ->from($data)
            ->download('products-with-totals.xlsx');
    }

    /**
     * Example 5.2: Totals with Full Configuration
     */
    public function totalsWithHeader()
    {
        $data = $this->getSampleInvoiceItems();

        return Exporter::make()
            ->format('xlsx')
            ->locale('en_IN')
            ->header(fn($h) => $h
                ->company('ABC Trading Co.')
                ->title('Invoice Summary')
                ->dateRange('01-Mar-2024', '31-Mar-2024')
            )
            ->columns(fn($cols) => $cols
                ->string('invoice_no', 'Invoice #')
                ->string('customer', 'Customer')
                ->quantity('items', 'Items')
                ->amountPlain('subtotal', 'Subtotal')
                ->amountPlain('tax', 'Tax')
                ->amount('total', 'Total')
            )
            ->withTotals(['items', 'subtotal', 'tax', 'total'])
            ->totalsLabel('TOTAL')
            ->from($data)
            ->download('invoice-summary.xlsx');
    }

    // =========================================================================
    // SECTION 6: MULTIPLE SHEETS
    // =========================================================================

    /**
     * Example 6.1: Basic Multiple Sheets
     */
    public function basicMultipleSheets()
    {
        $summaryData = [
            ['category' => 'Electronics', 'total_sales' => 250000, 'total_orders' => 45],
            ['category' => 'Clothing', 'total_sales' => 180000, 'total_orders' => 120],
            ['category' => 'Books', 'total_sales' => 75000, 'total_orders' => 200],
        ];

        $detailData = [
            ['order_no' => 'ORD-001', 'product' => 'Laptop', 'category' => 'Electronics', 'amount' => 85000],
            ['order_no' => 'ORD-002', 'product' => 'T-Shirt', 'category' => 'Clothing', 'amount' => 1500],
            ['order_no' => 'ORD-003', 'product' => 'Novel', 'category' => 'Books', 'amount' => 350],
        ];

        return Exporter::make()
            ->format('xlsx')
            ->sheet('Summary', fn($sheet) => $sheet
                ->data($summaryData)
                ->columns(fn($cols) => $cols
                    ->string('category', 'Category')
                    ->amount('total_sales', 'Total Sales')
                    ->integer('total_orders', 'Orders')
                )
                ->withTotals(['total_sales', 'total_orders'])
            )
            ->sheet('Details', fn($sheet) => $sheet
                ->data($detailData)
                ->columns(fn($cols) => $cols
                    ->string('order_no', 'Order #')
                    ->string('product', 'Product')
                    ->string('category', 'Category')
                    ->amount('amount', 'Amount')
                )
            )
            ->download('sales-multi-sheet.xlsx');
    }

    /**
     * Example 6.2: Sheets with Individual Headers
     */
    public function sheetsWithHeaders()
    {
        $jan = $this->getMonthlySales('January');
        $feb = $this->getMonthlySales('February');
        $mar = $this->getMonthlySales('March');

        return Exporter::make()
            ->format('xlsx')
            ->locale('en_IN')
            ->sheet('January', fn($sheet) => $sheet
                ->data($jan)
                ->header(fn($h) => $h
                    ->title('Sales Report - January 2024')
                    ->dateRange('01-Jan-2024', '31-Jan-2024')
                )
                ->columns(fn($cols) => $cols
                    ->date('date', 'Date')
                    ->string('customer', 'Customer')
                    ->amount('amount', 'Amount')
                )
                ->withTotals(['amount'])
            )
            ->sheet('February', fn($sheet) => $sheet
                ->data($feb)
                ->header(fn($h) => $h
                    ->title('Sales Report - February 2024')
                    ->dateRange('01-Feb-2024', '29-Feb-2024')
                )
                ->columns(fn($cols) => $cols
                    ->date('date', 'Date')
                    ->string('customer', 'Customer')
                    ->amount('amount', 'Amount')
                )
                ->withTotals(['amount'])
            )
            ->sheet('March', fn($sheet) => $sheet
                ->data($mar)
                ->header(fn($h) => $h
                    ->title('Sales Report - March 2024')
                    ->dateRange('01-Mar-2024', '31-Mar-2024')
                )
                ->columns(fn($cols) => $cols
                    ->date('date', 'Date')
                    ->string('customer', 'Customer')
                    ->amount('amount', 'Amount')
                )
                ->withTotals(['amount'])
            )
            ->download('quarterly-sales.xlsx');
    }

    /**
     * Example 6.3: Sheets from Grouped Data
     */
    public function sheetsFromGroupedData()
    {
        $orders = [
            ['order_no' => 'ORD-001', 'status' => 'Completed', 'amount' => 5000],
            ['order_no' => 'ORD-002', 'status' => 'Pending', 'amount' => 3500],
            ['order_no' => 'ORD-003', 'status' => 'Completed', 'amount' => 7500],
            ['order_no' => 'ORD-004', 'status' => 'Cancelled', 'amount' => 2000],
            ['order_no' => 'ORD-005', 'status' => 'Pending', 'amount' => 4500],
            ['order_no' => 'ORD-006', 'status' => 'Completed', 'amount' => 6000],
        ];

        return Exporter::make()
            ->format('xlsx')
            ->sheetsFromGroupedData(
                $orders,
                'status',
                fn($data, $status) => Sheet::make($status)
                    ->data($data)
                    ->header(fn($h) => $h->title("Orders - {$status}"))
                    ->columns(fn($cols) => $cols
                        ->string('order_no', 'Order #')
                        ->amount('amount', 'Amount')
                    )
                    ->withTotals(['amount'])
            )
            ->download('orders-by-status.xlsx');
    }

    /**
     * Example 6.4: Monthly Breakdown Sheets
     */
    public function monthlyBreakdownSheets()
    {
        $sales = [
            ['date' => '2024-01-15', 'customer' => 'ABC Corp', 'amount' => 15000],
            ['date' => '2024-01-22', 'customer' => 'XYZ Ltd', 'amount' => 22000],
            ['date' => '2024-02-05', 'customer' => 'PQR Inc', 'amount' => 18000],
            ['date' => '2024-02-18', 'customer' => 'ABC Corp', 'amount' => 25000],
            ['date' => '2024-03-10', 'customer' => 'LMN Co', 'amount' => 30000],
            ['date' => '2024-03-25', 'customer' => 'XYZ Ltd', 'amount' => 28000],
        ];

        return Exporter::make()
            ->format('xlsx')
            ->sheetsFromMonthlyData(
                $sales,
                'date',
                fn($data, $monthYear) => Sheet::make($monthYear)
                    ->data($data)
                    ->header(fn($h) => $h->title("Sales - {$monthYear}"))
                    ->columns(fn($cols) => $cols
                        ->date('date', 'Date')
                        ->string('customer', 'Customer')
                        ->amount('amount', 'Amount')
                    )
                    ->withTotals(['amount'])
            )
            ->download('monthly-sales.xlsx');
    }

    /**
     * Example 6.5: Summary + Details Pattern
     */
    public function summaryAndDetailsPattern()
    {
        $summary = [
            ['department' => 'Sales', 'employees' => 25, 'total_salary' => 750000],
            ['department' => 'IT', 'employees' => 15, 'total_salary' => 600000],
            ['department' => 'HR', 'employees' => 8, 'total_salary' => 280000],
        ];

        $employees = [
            ['name' => 'John', 'department' => 'Sales', 'salary' => 30000],
            ['name' => 'Jane', 'department' => 'IT', 'salary' => 45000],
            ['name' => 'Bob', 'department' => 'Sales', 'salary' => 28000],
            ['name' => 'Alice', 'department' => 'HR', 'salary' => 35000],
            ['name' => 'Charlie', 'department' => 'IT', 'salary' => 50000],
        ];

        return Exporter::make()
            ->format('xlsx')
            ->withSummaryAndDetails(
                fn() => Sheet::make('Summary')
                    ->data($summary)
                    ->header(fn($h) => $h
                        ->company('ABC Company')
                        ->title('Department-wise Salary Summary')
                    )
                    ->columns(fn($cols) => $cols
                        ->string('department', 'Department')
                        ->integer('employees', 'Employees')
                        ->amount('total_salary', 'Total Salary')
                    )
                    ->withTotals(['employees', 'total_salary']),
                $employees,
                'department',
                fn($data, $dept) => Sheet::make($dept)
                    ->data($data)
                    ->header(fn($h) => $h->title("{$dept} Department"))
                    ->columns(fn($cols) => $cols
                        ->string('name', 'Employee Name')
                        ->amount('salary', 'Salary')
                    )
                    ->withTotals(['salary'])
            )
            ->download('salary-report.xlsx');
    }

    // =========================================================================
    // SECTION 7: ROW TRANSFORMATION
    // =========================================================================

    /**
     * Example 7.1: Transform Rows
     */
    public function transformRows()
    {
        $data = $this->getSampleUsers();

        return Exporter::make()
            ->format('xlsx')
            ->columns(['id', 'name', 'email', 'status'])
            ->headers(['ID', 'Name', 'Email', 'Status'])
            ->transformRow(function (array $row, $original) {
                // Transform data before export
                $row['name'] = strtoupper($row['name']);
                $row['status'] = $original->is_active ?? true ? 'Active' : 'Inactive';
                return $row;
            })
            ->from($data)
            ->download('transformed-users.xlsx');
    }

    // =========================================================================
    // SECTION 8: ELOQUENT QUERY EXPORTS
    // =========================================================================

    /**
     * Example 8.1: Export from Eloquent Query
     * (Uncomment and use with your actual models)
     */
    public function eloquentQueryExport()
    {
        // Example with User model
        // return Exporter::make()
        //     ->format('xlsx')
        //     ->columns(fn($cols) => $cols
        //         ->integer('id', 'ID')
        //         ->string('name', 'Name')
        //         ->string('email', 'Email')
        //         ->date('created_at', 'Registered On')
        //     )
        //     ->from(User::query()->where('active', true))
        //     ->download('active-users.xlsx');
    }

    /**
     * Example 8.2: Export with Relations
     * (Uncomment and use with your actual models)
     */
    public function exportWithRelations()
    {
        // Example with Order model and customer relation
        // return Exporter::make()
        //     ->format('xlsx')
        //     ->columns(fn($cols) => $cols
        //         ->string('order_number', 'Order #')
        //         ->string('customer.name', 'Customer')      // Dot notation for relations
        //         ->string('customer.email', 'Email')
        //         ->amount('total_amount', 'Amount')
        //     )
        //     ->from(Order::with('customer')->latest())
        //     ->download('orders.xlsx');
    }

    // =========================================================================
    // SECTION 9: SAVING TO FILE
    // =========================================================================

    /**
     * Example 9.1: Save to Storage
     */
    public function saveToFile()
    {
        $data = $this->getSampleProducts();

        // Save to file instead of download
        $saved = Exporter::make()
            ->format('xlsx')
            ->columns(['code', 'name', 'price', 'stock'])
            ->from($data)
            ->toFile(storage_path('app/exports/products.xlsx'));

        return $saved; // Returns true on success
    }

    /**
     * Example 9.2: Get as String
     */
    public function getAsString()
    {
        $data = $this->getSampleProducts();

        // Get export content as string (useful for email attachments)
        $content = Exporter::make()
            ->format('csv')
            ->columns(['code', 'name', 'price'])
            ->from($data)
            ->toString();

        return $content;
    }

    // =========================================================================
    // SECTION 10: COMPLETE REAL-WORLD EXAMPLES
    // =========================================================================

    /**
     * Example 10.1: Complete Invoice Export
     */
    public function completeInvoiceExport()
    {
        $invoices = [
            [
                'invoice_no' => 'INV-2024-001',
                'invoice_date' => '2024-03-01',
                'customer_name' => 'ABC Corporation',
                'customer_gstin' => '27AABCU9603R1ZM',
                'subtotal' => 100000.00,
                'cgst' => 9000.00,
                'sgst' => 9000.00,
                'total' => 118000.00,
                'paid' => 118000.00,
                'balance' => 0.00,
            ],
            [
                'invoice_no' => 'INV-2024-002',
                'invoice_date' => '2024-03-05',
                'customer_name' => 'XYZ Traders',
                'customer_gstin' => '27AABCT1234R1ZP',
                'subtotal' => 75000.00,
                'cgst' => 6750.00,
                'sgst' => 6750.00,
                'total' => 88500.00,
                'paid' => 50000.00,
                'balance' => 38500.00,
            ],
        ];

        return Exporter::make()
            ->format('xlsx')
            ->locale('en_IN')
            ->header(fn($h) => $h
                ->company('Your Company Name Pvt. Ltd.')
                ->title('Sales Register')
                ->subtitle('GSTIN: 27AABCY1234R1ZX')
                ->dateRange('01-Mar-2024', '31-Mar-2024')
                ->generatedBy('Admin User')
                ->generatedAt()
            )
            ->columns(fn($cols) => $cols
                ->string('invoice_no', 'Invoice No.')
                ->date('invoice_date', 'Date')
                ->string('customer_name', 'Customer')
                ->string('customer_gstin', 'GSTIN')
                ->amountPlain('subtotal', 'Taxable Value')
                ->amountPlain('cgst', 'CGST')
                ->amountPlain('sgst', 'SGST')
                ->amount('total', 'Total')
                ->amountPlain('paid', 'Received')
                ->amount('balance', 'Balance')
            )
            ->withTotals(['subtotal', 'cgst', 'sgst', 'total', 'paid', 'balance'])
            ->totalsLabel('TOTAL')
            ->from($invoices)
            ->download('sales-register.xlsx');
    }

    /**
     * Example 10.2: Complete Stock Report with Multiple Sheets
     */
    public function completeStockReport()
    {
        $summary = [
            ['category' => 'Electronics', 'items' => 150, 'value' => 2500000],
            ['category' => 'Furniture', 'items' => 75, 'value' => 890000],
            ['category' => 'Stationery', 'items' => 500, 'value' => 125000],
        ];

        $electronics = [
            ['code' => 'EL001', 'name' => 'Laptop Dell', 'qty' => 25, 'rate' => 65000, 'value' => 1625000],
            ['code' => 'EL002', 'name' => 'Monitor LG', 'qty' => 50, 'rate' => 12000, 'value' => 600000],
            ['code' => 'EL003', 'name' => 'Keyboard', 'qty' => 75, 'rate' => 2000, 'value' => 150000],
        ];

        $furniture = [
            ['code' => 'FN001', 'name' => 'Office Chair', 'qty' => 30, 'rate' => 8000, 'value' => 240000],
            ['code' => 'FN002', 'name' => 'Office Desk', 'qty' => 25, 'rate' => 15000, 'value' => 375000],
            ['code' => 'FN003', 'name' => 'File Cabinet', 'qty' => 20, 'rate' => 12000, 'value' => 240000],
        ];

        return Exporter::make()
            ->format('xlsx')
            ->locale('en_IN')
            ->sheet('Summary', fn($sheet) => $sheet
                ->data($summary)
                ->header(fn($h) => $h
                    ->company('Inventory Management System')
                    ->title('Stock Valuation Summary')
                    ->asOnDate('31-Mar-2024')
                )
                ->columns(fn($cols) => $cols
                    ->string('category', 'Category')
                    ->integer('items', 'Total Items')
                    ->amount('value', 'Total Value')
                )
                ->withTotals(['items', 'value'])
            )
            ->sheet('Electronics', fn($sheet) => $sheet
                ->data($electronics)
                ->header(fn($h) => $h->title('Electronics Inventory'))
                ->columns(fn($cols) => $cols
                    ->string('code', 'Item Code')
                    ->string('name', 'Description')
                    ->quantity('qty', 'Quantity')
                    ->amountPlain('rate', 'Rate')
                    ->amount('value', 'Value')
                )
                ->withTotals(['qty', 'value'])
            )
            ->sheet('Furniture', fn($sheet) => $sheet
                ->data($furniture)
                ->header(fn($h) => $h->title('Furniture Inventory'))
                ->columns(fn($cols) => $cols
                    ->string('code', 'Item Code')
                    ->string('name', 'Description')
                    ->quantity('qty', 'Quantity')
                    ->amountPlain('rate', 'Rate')
                    ->amount('value', 'Value')
                )
                ->withTotals(['qty', 'value'])
            )
            ->download('stock-report.xlsx');
    }

    // =========================================================================
    // HELPER METHODS (Sample Data Generators)
    // =========================================================================

    private function getSampleUsers(): array
    {
        return [
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'created_at' => '2024-01-15'],
            ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'created_at' => '2024-02-20'],
            ['id' => 3, 'name' => 'Bob Wilson', 'email' => 'bob@example.com', 'created_at' => '2024-03-10'],
        ];
    }

    private function getSampleProducts(): array
    {
        return [
            ['code' => 'PRD001', 'name' => 'Widget A', 'price' => 1500.00, 'stock' => 100],
            ['code' => 'PRD002', 'name' => 'Widget B', 'price' => 2500.00, 'stock' => 75],
            ['code' => 'PRD003', 'name' => 'Widget C', 'price' => 3500.00, 'stock' => 50],
        ];
    }

    private function getSampleSales(): array
    {
        return [
            ['invoice_no' => 'INV-001', 'invoice_date' => '2024-03-01', 'customer' => 'ABC Corp', 'amount' => 15000],
            ['invoice_no' => 'INV-002', 'invoice_date' => '2024-03-05', 'customer' => 'XYZ Ltd', 'amount' => 22500],
            ['invoice_no' => 'INV-003', 'invoice_date' => '2024-03-10', 'customer' => 'PQR Inc', 'amount' => 18750],
        ];
    }

    private function getSampleInvoiceItems(): array
    {
        return [
            ['invoice_no' => 'INV-001', 'customer' => 'ABC Corp', 'items' => 5, 'subtotal' => 10000, 'tax' => 1800, 'total' => 11800],
            ['invoice_no' => 'INV-002', 'customer' => 'XYZ Ltd', 'items' => 3, 'subtotal' => 15000, 'tax' => 2700, 'total' => 17700],
            ['invoice_no' => 'INV-003', 'customer' => 'PQR Inc', 'items' => 8, 'subtotal' => 25000, 'tax' => 4500, 'total' => 29500],
        ];
    }

    private function getMonthlySales(string $month): array
    {
        $amounts = ['January' => [15000, 22000], 'February' => [18000, 25000], 'March' => [30000, 28000]];
        $values = $amounts[$month] ?? [10000, 12000];

        return [
            ['date' => '2024-01-15', 'customer' => 'ABC Corp', 'amount' => $values[0]],
            ['date' => '2024-01-22', 'customer' => 'XYZ Ltd', 'amount' => $values[1]],
        ];
    }
}
