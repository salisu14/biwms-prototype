<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the authoritative source currency to negotiated vendor prices.
     *
     * The column is nullable and is never backfilled. A NULL `currency_code`
     * means the historical price's source currency is unknown/ambiguous; such
     * rows must not be silently interpreted as the requesting document's
     * currency. Only new/explicitly-set prices carry a provenance currency.
     */
    public function up(): void
    {
        Schema::table('purchase_prices', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_prices', 'currency_code')) {
                $table->char('currency_code', 3)->nullable()->after('direct_unit_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_prices', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_prices', 'currency_code')) {
                $table->dropColumn('currency_code');
            }
        });
    }
};
