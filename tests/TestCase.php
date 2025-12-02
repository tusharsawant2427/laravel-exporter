<?php

namespace LaravelExporter\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use LaravelExporter\ExporterServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ExporterServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Exporter' => \LaravelExporter\Facades\Exporter::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
    }
}
