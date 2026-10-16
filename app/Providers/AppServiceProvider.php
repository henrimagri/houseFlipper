<?php

namespace App\Providers;

use App\Contracts\ItbiDatasetProviderInterface;
use App\Services\RealEstateData\SaoPauloItbiDatasetProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ItbiDatasetProviderInterface::class, SaoPauloItbiDatasetProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
