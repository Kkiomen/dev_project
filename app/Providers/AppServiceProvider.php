<?php

namespace App\Providers;

use App\Services\FieldTypeRegistry;
use App\Services\StockPhoto\PexelsClient;
use App\Services\StockPhoto\StockPhotoService;
use App\Services\StockPhoto\UnsplashClient;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FieldTypeRegistry::class, function () {
            return new FieldTypeRegistry();
        });

        $this->app->singleton(UnsplashClient::class);
        $this->app->singleton(PexelsClient::class);
        $this->app->singleton(StockPhotoService::class, function ($app) {
            return new StockPhotoService(
                $app->make(UnsplashClient::class),
                $app->make(PexelsClient::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
