---
layout: default
title: Home
---

# Laravel Exporter Documentation

> A fluent, memory-efficient data export/import package for Laravel supporting CSV, Excel, and JSON formats with rich formatting capabilities.

**Version:** 1.0.0  
**Author:** Tushar Sawant  
**License:** MIT  
**Laravel:** 10.x, 11.x, 12.x  
**PHP:** 8.1+

---

## 📚 Documentation Index

### Getting Started
- [Installation Guide](./installation.md) - How to install and configure the package
- [Quick Start](./quick-start.md) - Get up and running in 5 minutes
- [Configuration](./configuration.md) - All configuration options explained

### Exporting Data
- [Fluent API Exports](./exports/fluent-api.md) - Quick inline exports
- [Class-Based Exports](./exports/class-based.md) - Reusable export classes (Maatwebsite-style)
- [Column Definitions](./exports/column-definitions.md) - Define column types and formatting
- [Styling & Formatting](./exports/styling.md) - Headers, colors, and styles
- [Large Datasets](./exports/large-datasets.md) - Memory-efficient exports for 100K+ rows
- [Multiple Sheets](./exports/multiple-sheets.md) - Multi-sheet exports

### Importing Data
- [Basic Imports](./imports/basic.md) - Import files into your application
- [Validation](./imports/validation.md) - Validate imported data
- [Error Handling](./imports/error-handling.md) - Handle failures gracefully
- [Batch Processing](./imports/batch-processing.md) - Efficient bulk imports

### Reference
- [Concerns Reference](./reference/concerns.md) - All available concerns/interfaces
- [API Reference](./reference/api.md) - Complete API documentation
- [Facades](./reference/facades.md) - Available facades

### Advanced Topics
- [Performance Optimization](./advanced/performance.md) - Tips for optimal performance
- [Custom Exporters](./advanced/custom-exporters.md) - Create custom format exporters
- [Events & Hooks](./advanced/events.md) - Hook into the export/import lifecycle
- [Testing](./advanced/testing.md) - How to test exports and imports

### Examples
- [Real-World Examples](./examples/README.md) - Production-ready examples

---

## 🚀 Quick Example

```php
use LaravelExporter\Facades\Exporter;

// Simple export
Exporter::make()
    ->columns(['id', 'name', 'email'])
    ->from(User::query())
    ->download('users.csv');

// Styled Excel export
Exporter::make()
    ->format('xlsx')
    ->columns(fn($cols) => $cols
        ->string('name', 'Name')
        ->amount('total', 'Amount')
        ->date('created_at', 'Date')
    )
    ->from(Order::query())
    ->download('orders.xlsx');
```

---

## 📋 Feature Overview

| Feature | CSV | Excel | JSON |
|---------|-----|-------|------|
| Basic Export | ✅ | ✅ | ✅ |
| Column Headers | ✅ | ✅ | ✅ |
| Row Mapping | ✅ | ✅ | ✅ |
| Streaming (Large Files) | ✅ | ✅ | ✅ |
| Column Formatting | ❌ | ✅ | ❌ |
| Conditional Coloring | ❌ | ✅ | ❌ |
| Report Headers | ❌ | ✅ | ❌ |
| Totals Row | ❌ | ✅ | ❌ |
| Multiple Sheets | ❌ | ✅ | ❌ |
| Freeze Panes | ❌ | ✅ | ❌ |
| Auto Filter | ❌ | ✅ | ❌ |

---

## 🔗 Related Links

- [GitHub Repository](https://github.com/tusharsawant2427/laravel-exporter)
- [Packagist](https://packagist.org/packages/datasuite/laravel-exporter)
- [Changelog](../CHANGELOG.md)
- [License](../LICENSE)
