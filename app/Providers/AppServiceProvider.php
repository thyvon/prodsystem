<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\LaravelPdf\Facades\Pdf;   // ← important

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fix Chrome sandbox error on shared hosting / production servers
        // This makes spatie/laravel-pdf work perfectly everywhere
        Pdf::configureBrowser(function (\Spatie\Browsershot\Browsershot $browsershot) {
            $browsershot
                ->noSandbox()
                ->setOption('args', [
                    '--disable-gpu',
                    '--disable-dev-shm-usage',
                    '--no-zygote',
                    '--single-process',
                    '--disable-setuid-sandbox',
                ]);
        });
    }
}