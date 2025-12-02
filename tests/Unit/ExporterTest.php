<?php

namespace LaravelExporter\Tests\Unit;

use LaravelExporter\Exporter;
use LaravelExporter\Tests\TestCase;

class ExporterTest extends TestCase
{
    public function test_can_create_exporter_instance(): void
    {
        $exporter = Exporter::make();

        $this->assertInstanceOf(Exporter::class, $exporter);
    }

    public function test_can_set_format(): void
    {
        $exporter = Exporter::make()->format('xlsx');

        $this->assertEquals('xlsx', $exporter->getFormat());
    }

    public function test_can_set_columns(): void
    {
        $exporter = Exporter::make()->columns(['id', 'name']);

        $this->assertEquals(['id', 'name'], $exporter->getColumns());
    }

    public function test_can_set_headers(): void
    {
        $exporter = Exporter::make()->headers(['ID', 'Name']);

        $this->assertEquals(['ID', 'Name'], $exporter->getHeaders());
    }

    public function test_can_set_chunk_size(): void
    {
        $exporter = Exporter::make()->chunkSize(500);

        $this->assertEquals(500, $exporter->getChunkSize());
    }

    public function test_can_set_filename(): void
    {
        $exporter = Exporter::make()->filename('users');

        $this->assertEquals('users', $exporter->getFilename());
    }

    public function test_can_set_options(): void
    {
        $options = ['delimiter' => ';'];
        $exporter = Exporter::make()->options($options);

        $this->assertEquals($options, $exporter->getFormatOptions());
    }

    public function test_format_shortcuts(): void
    {
        $this->assertEquals('csv', Exporter::make()->asCsv()->getFormat());
        $this->assertEquals('xlsx', Exporter::make()->asExcel()->getFormat());
        $this->assertEquals('json', Exporter::make()->asJson()->getFormat());
    }

    public function test_can_export_array_to_csv_string(): void
    {
        $data = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];

        $output = Exporter::make()
            ->format('csv')
            ->from($data)
            ->toString();

        $this->assertStringContainsString('Id,Name', $output);
        $this->assertStringContainsString('1,John', $output);
        $this->assertStringContainsString('2,Jane', $output);
    }

    public function test_can_export_to_json(): void
    {
        $data = [
            ['id' => 1, 'name' => 'John'],
        ];

        $output = Exporter::make()
            ->format('json')
            ->from($data)
            ->toString();

        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertEquals(1, $decoded[0]['id']);
        $this->assertEquals('John', $decoded[0]['name']);
    }

    public function test_can_filter_columns(): void
    {
        $data = [
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
        ];

        $output = Exporter::make()
            ->format('json')
            ->columns(['id', 'name'])
            ->from($data)
            ->toString();

        $decoded = json_decode($output, true);

        $this->assertArrayHasKey('id', $decoded[0]);
        $this->assertArrayHasKey('name', $decoded[0]);
        $this->assertArrayNotHasKey('email', $decoded[0]);
    }

    public function test_can_transform_rows(): void
    {
        $data = [
            ['id' => 1, 'name' => 'john'],
        ];

        $output = Exporter::make()
            ->format('json')
            ->transformRow(fn($row) => [
                ...$row,
                'name' => strtoupper($row['name']),
            ])
            ->from($data)
            ->toString();

        $decoded = json_decode($output, true);

        $this->assertEquals('JOHN', $decoded[0]['name']);
    }
}
