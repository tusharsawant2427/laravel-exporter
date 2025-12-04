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

        // Register the fluent Exporter class
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

        // Register the Maatwebsite-style Excel class
        $this->app->singleton('laravel-exporter', function ($app) {
            return new Excel();
        });

        $this->app->alias('laravel-exporter', Excel::class);
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
            Excel::class,
            'laravel-exporter',
        ];
    }
}
