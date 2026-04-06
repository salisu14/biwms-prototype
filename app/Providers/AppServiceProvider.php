<?php

namespace App\Providers;

use App\Models\SalesCreditMemoLine;
use App\Observers\SalesCreditMemoLineObserver;
use Illuminate\Support\ServiceProvider;

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
        SalesCreditMemoLine::observe(SalesCreditMemoLineObserver::class);
    }
}
