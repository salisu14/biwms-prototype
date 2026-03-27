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
        Schema::create('item_skus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')
                ->constrained('item_masters', 'id')
                ->onDelete('cascade');
            $table->foreignId('location_id')
                ->constrained('location_masters', 'id')
                ->onDelete('cascade');
            $table->string('sku_code', 50)->unique();
            $table->decimal('reorder_point', 18, 4)->default(0);
            $table->decimal('safety_stock', 18, 4)->default(0);

            $table->string('barcode', 50)->nullable()->unique()->after('sku_code');
            $table->integer('lead_time_days')->nullable()->after('safety_stock');
            $table->date('effective_date')->nullable()->after('is_active');
            $table->date('expiry_date')->nullable()->after('effective_date');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Prevent duplicate item-location combinations
            $table->unique(['item_id', 'location_id']);
            $table->index(['sku_code', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_skus');
    }
};
