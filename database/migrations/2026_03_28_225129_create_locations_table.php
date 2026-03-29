<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('address')->nullable();

            // WMS Complexity Settings
            $table->boolean('directed_put_away_and_pick')->default(false);
            $table->boolean('bin_mandatory')->default(false);
            $table->boolean('require_receive')->default(false);
            $table->boolean('require_shipment')->default(false);
            $table->boolean('require_put_away')->default(false);
            $table->boolean('require_pick')->default(false);

            // Default bins
            $table->string('receipt_bin_code', 20)->nullable();
            $table->string('shipment_bin_code', 20)->nullable();
            $table->string('open_shop_floor_bin_code', 20)->nullable();
            $table->string('inbound_production_bin_code', 20)->nullable();
            $table->string('outbound_production_bin_code', 20)->nullable();
            $table->string('adjustment_bin_code', 20)->nullable();

            $table->boolean('blocked')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
