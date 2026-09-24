<?php

namespace LaravelExporter\Tests\Unit;

use LaravelExporter\Exporter;
use LaravelExporter\Formats\HybridExporter;
use LaravelExporter\Formats\StyledOpenSpoutExporter;
use LaravelExporter\Tests\TestCase;

class OpenSpoutStyleCompatibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\OpenSpout\Writer\XLSX\Writer::class)) {
            $this->markTestSkipped('openspout/openspout is not installed.');
        }
    }

    protected function sampleData(): array
    {
        return [
            ['id' => 1, 'name' => 'John', 'amount' => 100.5],
            ['id' => 2, 'name' => 'Jane', 'amount' => -50.25],
        ];
    }

    public function test_excel_exporter_writes_xlsx_with_styled_headers_via_openspout(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'exporter_test_') . '.xlsx';

        $result = Exporter::make()
            ->format('xlsx')
            ->columns(['id', 'name', 'amount'])
            ->from($this->sampleData())
            ->toFile($path);

        $this->assertTrue($result);
        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));

        unlink($path);
    }

    public function test_styled_openspout_exporter_writes_xlsx(): void
    {
        $exporter = new StyledOpenSpoutExporter(['bold_headers' => true]);
        $path = tempnam(sys_get_temp_dir(), 'exporter_test_') . '.xlsx';

        $exporter->export($this->generatorFor($this->sampleData()), ['ID', 'Name', 'Amount'], $path);

        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));

        unlink($path);
    }

    public function test_hybrid_exporter_writes_xlsx_with_frozen_header_and_autofilter(): void
    {
        $exporter = new HybridExporter();
        $path = tempnam(sys_get_temp_dir(), 'exporter_test_') . '.xlsx';

        $exporter->export($this->generatorFor($this->sampleData()), ['ID', 'Name', 'Amount'], $path);

        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));

        unlink($path);
    }

    protected function generatorFor(array $rows): \Generator
    {
        foreach ($rows as $row) {
            yield $row;
        }
    }
}
