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
        Schema::create('item_masters', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 50)->unique();
            $table->string('description', 255);
            $table->decimal('standard_cost', 18, 4)->default(0);

            $table->decimal('reference_cost', 15, 4)->default(0)->after('inventory_method');
            $table->decimal('reference_price', 15, 4)->default(0)->after('reference_cost');

            $table->integer('shelf_life_days')->nullable();
            $table->boolean('is_active')->default(true);

            $table->string('item_type', 20)->default('RAW_MATERIAL')->after('description');
            $table->string('inventory_method', 10)->default('FIFO')->after('item_type');

            $table->foreignId('vat_id')->nullable()->after('inventory_method')->constrained('vat_masters');
            $table->foreignId('general_posting_setup_id')->nullable()->after('vat_id');
            $table->foreignId('inventory_posting_setup_id')->nullable()->after('general_posting_setup_id');

            $table->foreign('general_posting_setup_id')->references('id')->on('general_posting_setups');
            $table->foreign('inventory_posting_setup_id')->references('id')->on('inventory_posting_setups');

            $table->timestamps();

            $table->index(['item_code', 'is_active']);
            $table->index('item_type');
            $table->index('inventory_method');
            $table->index(['item_type', 'inventory_method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_masters');
    }
};
