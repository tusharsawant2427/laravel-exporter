# Creating Custom Exporters

This guide explains how to create custom format exporters to support additional output formats or customize existing behavior.

## Table of Contents

1. [Understanding the Exporter Architecture](#understanding-the-exporter-architecture)
2. [Creating a Basic Exporter](#creating-a-basic-exporter)
3. [Implementing Required Methods](#implementing-required-methods)
4. [Adding Styling Support](#adding-styling-support)
5. [Registering Your Exporter](#registering-your-exporter)
6. [Real-World Examples](#real-world-examples)

---

## Understanding the Exporter Architecture

The package uses a driver-based architecture for format exporters. Each format has its own exporter class that implements the `FormatExporterInterface`.

### Core Interface

```php
namespace DataSuite\LaravelExporter\Contracts;

interface FormatExporterInterface
{
    /**
     * Export data and return the file path
     */
    public function export(mixed $source, string $filePath, array $options = []): string;

    /**
     * Get the file extension for this format
     */
    public function getExtension(): string;

    /**
     * Get the MIME type for this format
     */
    public function getMimeType(): string;

    /**
     * Check if this exporter supports a feature
     */
    public function supports(string $feature): bool;
}
```

### Available Built-in Exporters

| Exporter | Format | Styling | Large Datasets |
|----------|--------|---------|----------------|
| `CsvExporter` | CSV | No | Yes |
| `JsonExporter` | JSON | No | Yes |
| `ExcelExporter` | XLSX (Native) | Basic | Yes |
| `PhpSpreadsheetExporter` | XLSX | Full | Limited |
| `HybridExporter` | XLSX | Smart | Yes |

---

## Creating a Basic Exporter

### Step 1: Create the Exporter Class

```php
<?php

namespace App\Exporters;

use DataSuite\LaravelExporter\Contracts\FormatExporterInterface;
use DataSuite\LaravelExporter\Support\ColumnCollection;
use Illuminate\Support\Collection;

class XmlExporter implements FormatExporterInterface
{
    protected array $options = [];

    public function export(mixed $source, string $filePath, array $options = []): string
    {
        $this->options = $options;
        
        $data = $this->normalizeSource($source);
        $xml = $this->buildXml($data);
        
        file_put_contents($filePath, $xml);
        
        return $filePath;
    }

    public function getExtension(): string
    {
        return 'xml';
    }

    public function getMimeType(): string
    {
        return 'application/xml';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, [
            'headings',
            'mapping',
            'column_definitions',
        ]);
    }

    protected function normalizeSource(mixed $source): Collection
    {
        if ($source instanceof Collection) {
            return $source;
        }

        if (is_array($source)) {
            return collect($source);
        }

        // Handle query builders, generators, etc.
        return collect($source);
    }

    protected function buildXml(Collection $data): string
    {
        $rootElement = $this->options['root_element'] ?? 'data';
        $rowElement = $this->options['row_element'] ?? 'row';
        
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement($rootElement);
        
        // Add metadata
        if (isset($this->options['metadata'])) {
            $xml->startElement('metadata');
            foreach ($this->options['metadata'] as $key => $value) {
                $xml->writeElement($key, $value);
            }
            $xml->endElement();
        }
        
        // Add headings as schema
        if (isset($this->options['headings'])) {
            $xml->startElement('schema');
            foreach ($this->options['headings'] as $index => $heading) {
                $xml->startElement('column');
                $xml->writeAttribute('index', $index);
                $xml->writeAttribute('name', $heading);
                $xml->endElement();
            }
            $xml->endElement();
        }
        
        // Add data rows
        $xml->startElement('rows');
        foreach ($data as $row) {
            $xml->startElement($rowElement);
            
            if (isset($this->options['headings'])) {
                // Use headings as element names
                foreach ($this->options['headings'] as $index => $heading) {
                    $elementName = $this->sanitizeElementName($heading);
                    $value = $row[$index] ?? $row[$heading] ?? '';
                    $xml->writeElement($elementName, $this->formatValue($value));
                }
            } else {
                // Use numeric indices
                foreach ($row as $key => $value) {
                    $xml->writeElement("field_{$key}", $this->formatValue($value));
                }
            }
            
            $xml->endElement();
        }
        $xml->endElement(); // rows
        
        $xml->endElement(); // root
        $xml->endDocument();
        
        return $xml->outputMemory();
    }

    protected function sanitizeElementName(string $name): string
    {
        // XML element names must start with letter or underscore
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        
        if (preg_match('/^[0-9]/', $name)) {
            $name = '_' . $name;
        }
        
        return strtolower($name);
    }

    protected function formatValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('c'); // ISO 8601
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return '';
        }

        return (string) $value;
    }
}
```

### Step 2: Add Configuration Options

```php
<?php

namespace App\Exporters;

class XmlExporter implements FormatExporterInterface
{
    protected array $defaultOptions = [
        'root_element' => 'data',
        'row_element' => 'row',
        'include_schema' => true,
        'pretty_print' => true,
        'encoding' => 'UTF-8',
    ];

    public function export(mixed $source, string $filePath, array $options = []): string
    {
        $this->options = array_merge($this->defaultOptions, $options);
        
        // ... rest of export logic
    }
}
```

---

## Implementing Required Methods

### Handling Different Source Types

```php
protected function normalizeSource(mixed $source): Collection
{
    // Already a collection
    if ($source instanceof Collection) {
        return $source;
    }

    // Eloquent query builder
    if ($source instanceof \Illuminate\Database\Eloquent\Builder) {
        return $source->get();
    }

    // Database query builder
    if ($source instanceof \Illuminate\Database\Query\Builder) {
        return collect($source->get());
    }

    // Generator
    if ($source instanceof \Generator) {
        return collect(iterator_to_array($source));
    }

    // Callable
    if (is_callable($source)) {
        return $this->normalizeSource($source());
    }

    // Array
    if (is_array($source)) {
        return collect($source);
    }

    throw new \InvalidArgumentException('Unsupported data source type');
}
```

### Supporting Column Definitions

```php
protected function applyColumnDefinitions(Collection $data): Collection
{
    if (!isset($this->options['column_definitions'])) {
        return $data;
    }

    /** @var ColumnCollection $columns */
    $columns = $this->options['column_definitions'];

    return $data->map(function ($row) use ($columns) {
        $formatted = [];
        
        foreach ($columns->all() as $index => $column) {
            $value = $row[$index] ?? $row[$column->name] ?? null;
            $formatted[$column->name] = $this->formatByType($value, $column);
        }
        
        return $formatted;
    });
}

protected function formatByType(mixed $value, ColumnDefinition $column): mixed
{
    return match ($column->type) {
        'integer' => (int) $value,
        'amount', 'amountPlain' => number_format((float) $value, $column->decimals ?? 2, '.', ''),
        'percentage' => number_format((float) $value, $column->decimals ?? 2, '.', '') . '%',
        'date' => $value instanceof \DateTimeInterface 
            ? $value->format($column->format ?? 'Y-m-d') 
            : $value,
        'datetime' => $value instanceof \DateTimeInterface 
            ? $value->format($column->format ?? 'Y-m-d H:i:s') 
            : $value,
        'boolean' => $value ? 'Yes' : 'No',
        default => $value,
    };
}
```

### Memory-Efficient Streaming

```php
protected function buildXmlStreaming(iterable $data, string $filePath): string
{
    $handle = fopen($filePath, 'w');
    
    $xml = new \XMLWriter();
    $xml->openURI($filePath);
    $xml->setIndent($this->options['pretty_print']);
    
    $xml->startDocument('1.0', $this->options['encoding']);
    $xml->startElement($this->options['root_element']);
    $xml->startElement('rows');
    
    foreach ($data as $row) {
        $xml->startElement($this->options['row_element']);
        
        foreach ($row as $key => $value) {
            $xml->writeElement(
                $this->sanitizeElementName($key),
                $this->formatValue($value)
            );
        }
        
        $xml->endElement();
        
        // Flush periodically to free memory
        if ($xml->startElement('_flush')) {
            $xml->flush();
        }
    }
    
    $xml->endElement(); // rows
    $xml->endElement(); // root
    $xml->endDocument();
    
    return $filePath;
}
```

---

## Adding Styling Support

For formats that support styling (like XML with XSLT), you can add styling capabilities:

```php
class StyledXmlExporter extends XmlExporter
{
    public function supports(string $feature): bool
    {
        return in_array($feature, [
            'headings',
            'mapping',
            'column_definitions',
            'stylesheet',  // XSLT support
        ]);
    }

    protected function buildXml(Collection $data): string
    {
        $xml = parent::buildXml($data);
        
        // Add XSLT processing instruction if stylesheet provided
        if (isset($this->options['stylesheet'])) {
            $styleInstruction = '<?xml-stylesheet type="text/xsl" href="' 
                . $this->options['stylesheet'] . '"?>';
            
            $xml = preg_replace(
                '/<\?xml[^?]+\?>/', 
                '$0' . "\n" . $styleInstruction, 
                $xml
            );
        }
        
        return $xml;
    }
}
```

---

## Registering Your Exporter

### Via Service Provider

```php
<?php

namespace App\Providers;

use App\Exporters\XmlExporter;
use DataSuite\LaravelExporter\ExporterManager;
use Illuminate\Support\ServiceProvider;

class ExporterServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Register with the exporter manager
        $this->app->afterResolving(ExporterManager::class, function ($manager) {
            $manager->extend('xml', function () {
                return new XmlExporter();
            });
        });
    }
}
```

### Via Configuration

```php
// config/exporter.php
return [
    'formats' => [
        'xml' => \App\Exporters\XmlExporter::class,
        'pdf' => \App\Exporters\PdfExporter::class,
    ],
];
```

### Dynamic Registration

```php
use DataSuite\LaravelExporter\Facades\Exporter;

// At runtime
Exporter::registerFormat('xml', new XmlExporter());

// Usage
Exporter::make()
    ->data($data)
    ->format('xml')
    ->download();
```

---

## Real-World Examples

### HTML Table Exporter

```php
<?php

namespace App\Exporters;

use DataSuite\LaravelExporter\Contracts\FormatExporterInterface;
use Illuminate\Support\Collection;

class HtmlTableExporter implements FormatExporterInterface
{
    protected array $options = [];

    public function export(mixed $source, string $filePath, array $options = []): string
    {
        $this->options = array_merge([
            'title' => 'Export',
            'table_class' => 'table table-striped',
            'include_styles' => true,
        ], $options);

        $data = collect($source);
        $html = $this->buildHtml($data);
        
        file_put_contents($filePath, $html);
        
        return $filePath;
    }

    public function getExtension(): string
    {
        return 'html';
    }

    public function getMimeType(): string
    {
        return 'text/html';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, [
            'headings',
            'mapping',
            'column_definitions',
            'conditional_coloring',
            'report_header',
        ]);
    }

    protected function buildHtml(Collection $data): string
    {
        $html = '<!DOCTYPE html><html><head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>' . htmlspecialchars($this->options['title']) . '</title>';
        
        if ($this->options['include_styles']) {
            $html .= $this->getStyles();
        }
        
        $html .= '</head><body>';
        
        // Report header
        if (isset($this->options['report_header'])) {
            $html .= $this->buildReportHeader($this->options['report_header']);
        }
        
        // Table
        $html .= '<table class="' . $this->options['table_class'] . '">';
        
        // Headings
        if (isset($this->options['headings'])) {
            $html .= '<thead><tr>';
            foreach ($this->options['headings'] as $heading) {
                $html .= '<th>' . htmlspecialchars($heading) . '</th>';
            }
            $html .= '</tr></thead>';
        }
        
        // Body
        $html .= '<tbody>';
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $key => $value) {
                $style = $this->getCellStyle($key, $value);
                $html .= '<td' . ($style ? ' style="' . $style . '"' : '') . '>';
                $html .= htmlspecialchars($this->formatValue($value));
                $html .= '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        
        // Totals
        if (isset($this->options['totals'])) {
            $html .= $this->buildTotalsRow($data);
        }
        
        $html .= '</table></body></html>';
        
        return $html;
    }

    protected function getStyles(): string
    {
        return <<<CSS
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .table { width: 100%; border-collapse: collapse; }
            .table th, .table td { padding: 8px; border: 1px solid #ddd; text-align: left; }
            .table th { background: #f5f5f5; font-weight: bold; }
            .table-striped tbody tr:nth-child(odd) { background: #fafafa; }
            .report-header { margin-bottom: 20px; }
            .report-title { font-size: 24px; margin-bottom: 10px; }
            .totals-row { font-weight: bold; background: #e9ecef; }
        </style>
        CSS;
    }

    protected function buildReportHeader($header): string
    {
        $html = '<div class="report-header">';
        $html .= '<h1 class="report-title">' . htmlspecialchars($header->title ?? '') . '</h1>';
        
        if (!empty($header->info)) {
            $html .= '<table class="header-info">';
            foreach ($header->info as $label => $value) {
                $html .= '<tr><th>' . htmlspecialchars($label) . ':</th>';
                $html .= '<td>' . htmlspecialchars($value) . '</td></tr>';
            }
            $html .= '</table>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    protected function getCellStyle(string $column, mixed $value): ?string
    {
        if (!isset($this->options['conditional_coloring'][$column])) {
            return null;
        }

        $colors = $this->options['conditional_coloring'][$column];
        
        foreach ($colors as $condition => $color) {
            if ($value === $condition) {
                return "background-color: {$color}; color: white;";
            }
        }

        return null;
    }

    protected function formatValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        
        return (string) ($value ?? '');
    }

    protected function buildTotalsRow(Collection $data): string
    {
        $totals = [];
        $headings = $this->options['headings'] ?? [];
        $totalColumns = $this->options['totals'] ?? [];

        foreach ($totalColumns as $column) {
            $index = array_search($column, $headings);
            if ($index !== false) {
                $totals[$index] = $data->sum(fn($row) => (float) ($row[$index] ?? 0));
            }
        }

        $html = '<tfoot><tr class="totals-row">';
        
        foreach ($headings as $index => $heading) {
            if ($index === 0) {
                $html .= '<td>Total</td>';
            } elseif (isset($totals[$index])) {
                $html .= '<td>' . number_format($totals[$index], 2) . '</td>';
            } else {
                $html .= '<td></td>';
            }
        }
        
        $html .= '</tr></tfoot>';
        
        return $html;
    }
}
```

### PDF Exporter (using DomPDF)

```php
<?php

namespace App\Exporters;

use DataSuite\LaravelExporter\Contracts\FormatExporterInterface;
use Dompdf\Dompdf;
use Illuminate\Support\Collection;

class PdfExporter implements FormatExporterInterface
{
    protected HtmlTableExporter $htmlExporter;
    protected array $options = [];

    public function __construct()
    {
        $this->htmlExporter = new HtmlTableExporter();
    }

    public function export(mixed $source, string $filePath, array $options = []): string
    {
        $this->options = array_merge([
            'paper' => 'a4',
            'orientation' => 'portrait',
        ], $options);

        // Generate HTML first
        $tempHtml = tempnam(sys_get_temp_dir(), 'export') . '.html';
        $this->htmlExporter->export($source, $tempHtml, $options);
        
        // Convert to PDF
        $dompdf = new Dompdf();
        $dompdf->loadHtml(file_get_contents($tempHtml));
        $dompdf->setPaper($this->options['paper'], $this->options['orientation']);
        $dompdf->render();
        
        file_put_contents($filePath, $dompdf->output());
        
        // Cleanup
        unlink($tempHtml);
        
        return $filePath;
    }

    public function getExtension(): string
    {
        return 'pdf';
    }

    public function getMimeType(): string
    {
        return 'application/pdf';
    }

    public function supports(string $feature): bool
    {
        return $this->htmlExporter->supports($feature);
    }
}
```

### Markdown Exporter

```php
<?php

namespace App\Exporters;

use DataSuite\LaravelExporter\Contracts\FormatExporterInterface;
use Illuminate\Support\Collection;

class MarkdownExporter implements FormatExporterInterface
{
    protected array $options = [];

    public function export(mixed $source, string $filePath, array $options = []): string
    {
        $this->options = $options;
        
        $data = collect($source);
        $markdown = $this->buildMarkdown($data);
        
        file_put_contents($filePath, $markdown);
        
        return $filePath;
    }

    public function getExtension(): string
    {
        return 'md';
    }

    public function getMimeType(): string
    {
        return 'text/markdown';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, ['headings', 'mapping', 'report_header']);
    }

    protected function buildMarkdown(Collection $data): string
    {
        $md = '';
        
        // Report header
        if (isset($this->options['report_header'])) {
            $header = $this->options['report_header'];
            $md .= "# {$header->title}\n\n";
            
            if (!empty($header->info)) {
                foreach ($header->info as $label => $value) {
                    $md .= "**{$label}:** {$value}  \n";
                }
                $md .= "\n";
            }
        }
        
        // Table
        $headings = $this->options['headings'] ?? [];
        
        if (!empty($headings)) {
            // Header row
            $md .= '| ' . implode(' | ', $headings) . " |\n";
            // Separator
            $md .= '| ' . implode(' | ', array_fill(0, count($headings), '---')) . " |\n";
        }
        
        // Data rows
        foreach ($data as $row) {
            $cells = [];
            foreach ($row as $value) {
                $cells[] = str_replace(['|', "\n"], ['\\|', ' '], (string) $value);
            }
            $md .= '| ' . implode(' | ', $cells) . " |\n";
        }
        
        return $md;
    }
}
```

---

## Testing Custom Exporters

```php
<?php

namespace Tests\Unit;

use App\Exporters\XmlExporter;
use PHPUnit\Framework\TestCase;

class XmlExporterTest extends TestCase
{
    protected XmlExporter $exporter;
    protected string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporter = new XmlExporter();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'test') . '.xml';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        parent::tearDown();
    }

    public function test_exports_collection_to_xml()
    {
        $data = collect([
            ['name' => 'Product 1', 'price' => 10.00],
            ['name' => 'Product 2', 'price' => 20.00],
        ]);

        $path = $this->exporter->export($data, $this->tempFile, [
            'headings' => ['name', 'price'],
        ]);

        $this->assertFileExists($path);
        
        $xml = simplexml_load_file($path);
        $this->assertCount(2, $xml->rows->row);
    }

    public function test_returns_correct_extension()
    {
        $this->assertEquals('xml', $this->exporter->getExtension());
    }

    public function test_returns_correct_mime_type()
    {
        $this->assertEquals('application/xml', $this->exporter->getMimeType());
    }

    public function test_supports_headings_feature()
    {
        $this->assertTrue($this->exporter->supports('headings'));
    }

    public function test_does_not_support_styling_feature()
    {
        $this->assertFalse($this->exporter->supports('styling'));
    }
}
```

---

[← Performance](./performance.md) | [Back to Documentation](../INDEX.md) | [Events →](./events.md)
