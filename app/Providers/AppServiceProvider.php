<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\GlEntry;
use App\Models\SalesCreditMemoLine;
use App\Models\Vendor;
use App\Observers\GlEntryObserver;
use App\Observers\SalesCreditMemoLineObserver;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        Relation::morphMap([
            'CUSTOMER' => Customer::class,
            'VENDOR' => Vendor::class,
        ]);

        SalesCreditMemoLine::observe(SalesCreditMemoLineObserver::class);
        GlEntry::observe(GlEntryObserver::class);
    }
}
