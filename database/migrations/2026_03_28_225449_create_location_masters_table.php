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
        Schema::create('location_masters', function (Blueprint $table) {
            $table->id();
            $table->string('location_code', 20)->unique();
            $table->string('location_name', 100);
            $table->enum('location_type', [
                'RECEIVING',
                'QUARANTINE',
                'APPROVED',
                'PRODUCTION',
                'SHIPPING',
                'RETURNS'
            ])->default('APPROVED');
            $table->enum('temperature_zone', [
                'AMBIENT',
                'COOL',
                'COLD',
                'FROZEN'
            ])->default('AMBIENT');
            $table->boolean('is_active')->default(true);

            $table->foreignId('parent_id')
                ->nullable()
                ->after('id') // or after any existing column
                ->constrained('location_masters')
                ->onDelete('set null');

            $table->integer('sort_order')->default(0)->after('temperature_zone');

            // UOM fields
            $table->foreignId('sales_uom_id')
                ->nullable()
                ->constrained('unit_of_measures')
                ->after('base_uom_id');
            $table->foreignId('purchase_uom_id')
                ->nullable()
                ->constrained('unit_of_measures')
                ->after('sales_uom_id');

            // Pricing fields
            $table->decimal('unit_price', 15, 4)->nullable()->after('standard_cost');

            // Vendor fields
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->after('unit_price');
            $table->string('vendor_item_number', 50)->nullable()->after('vendor_id');

            $table->timestamps();

            $table->index(['location_code', 'is_active']);
            $table->index(['location_type', 'temperature_zone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_masters');
    }
};
