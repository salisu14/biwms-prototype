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
        Schema::create('vendor_items', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('vendor_id')->constrained('vendors')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('item_masters')->onDelete('cascade');

            // Vendor's item identification
            $table->string('vendor_item_number', 50);
            $table->string('vendor_item_name', 255)->nullable();
            $table->string('vendor_item_category', 50)->nullable();

            // Pricing (in vendor's currency)
            $table->decimal('unit_cost', 15, 4);
            $table->char('currency', 3)->default('USD');
            $table->json('price_breaks')->nullable(); // Quantity discounts

            // Ordering constraints
            $table->decimal('minimum_order_qty', 15, 4)->default(1);
            $table->integer('lead_time_days')->default(0);

            // Status
            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_active')->default(true);

            // Validity period
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();

            // History
            $table->date('last_purchase_date')->nullable();
            $table->decimal('last_purchase_price', 15, 4)->nullable();

            $table->timestamps();

            // Unique: One vendor-item combo, but allow multiple vendors per item
            $table->unique(['vendor_id', 'item_id']);

            // Indexes
            $table->index('item_id');
            $table->index(['item_id', 'is_preferred']);
            $table->index(['vendor_id', 'is_active']);
            $table->index('vendor_item_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_items');
    }
};
