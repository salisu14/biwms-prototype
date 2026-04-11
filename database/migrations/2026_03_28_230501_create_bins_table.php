<?php

use App\Enums\BinType;
use App\Enums\WarehouseClass;
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
        Schema::create('bins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();

            $table->string('bin_code', 20);
            $table->string('bin_name', 100)->nullable();
            $table->string('barcode', 50)->nullable();

            $table->enum('bin_type', array_column(BinType::cases(), 'value'))->default('STORAGE');
            $table->enum('warehouse_class', array_column(WarehouseClass::cases(), 'value'))->default('standard');

            // Capacity
            $table->decimal('maximum_weight', 15, 4)->nullable();
            $table->decimal('maximum_volume', 15, 4)->nullable();
            $table->integer('maximum_items')->nullable();

            // Status & Blocking
            $table->boolean('blocked')->default(false);
            $table->boolean('block_movement_in')->default(false);
            $table->boolean('block_movement_out')->default(false);
            $table->boolean('is_active')->default(true);

            // Dedication
            $table->boolean('dedicated')->default(false);
            $table->foreignId('dedicated_item_id')->nullable()->constrained('items')->nullOnDelete();

            $table->timestamps();

            $table->unique(['location_id', 'bin_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bins');
    }
};
