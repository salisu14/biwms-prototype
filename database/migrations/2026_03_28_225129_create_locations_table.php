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
            $table->foreignId('parent_id')->nullable()->constrained('locations')->onDelete('set null');
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('location_type', 30)->default('STORAGE'); // receiving, storage, picking, shipping, production
            $table->string('temperature_zone', 30)->default('AMBIENT'); // ambient, cool, cold, frozen
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

            $table->boolean('is_active')->default(true);
            $table->boolean('blocked')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['code', 'is_active']);
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
