<?php

namespace MostafaMoknaa\ArabicPdf;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class ArabicPdfServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/arabic-pdf.php', 'arabic-pdf');

        $this->app->singleton(Arabic::class, fn (Application $app): Arabic => new Arabic($app['config']->get('arabic-pdf', [])));

        $this->app->singleton(PdfFactory::class, fn (Application $app): PdfFactory => new PdfFactory(
            $app->make(Arabic::class),
            $app['config']->get('arabic-pdf', []),
            $app->make('view'),
        ));

        $this->app->alias(Arabic::class, 'arabic');
        $this->app->alias(PdfFactory::class, 'arabic-pdf');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/arabic-pdf.php' => config_path('arabic-pdf.php'),
            ], 'arabic-pdf-config');
        }

        Blade::directive('arabic', fn (string $expression): string => "<?php echo app('arabic')->fixForHtml({$expression}); ?>");
    }
}
