<?php

namespace LaravelExporter;

use Illuminate\Support\ServiceProvider;

class ExporterServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/exporter.php',
            'exporter'
        );

        $this->app->bind(Exporter::class, function ($app) {
            $exporter = new Exporter();

            // Apply default configuration
            $config = $app['config']->get('exporter', []);

            if (isset($config['default_format'])) {
                $exporter->format($config['default_format']);
            }

            if (isset($config['chunk_size'])) {
                $exporter->chunkSize($config['chunk_size']);
            }

            if (isset($config['csv'])) {
                $exporter->options($config['csv']);
            }

            return $exporter;
        });

        $this->app->alias(Exporter::class, 'exporter');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/exporter.php' => config_path('exporter.php'),
            ], 'exporter-config');
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            Exporter::class,
            'exporter',
        ];
    }
}
