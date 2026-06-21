<?php

use App\Enums\WarehouseClass;
use App\Enums\ZoneType;
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
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->string('zone_code', 20);
            $table->string('zone_name', 100);
            $table->string('description')->nullable();

            $table->enum('zone_type', array_column(ZoneType::cases(), 'value'))
                ->default('STORAGE');

            $table->enum('warehouse_class', array_column(WarehouseClass::cases(), 'value'))
                ->default('standard');

            $table->string('bin_type_code', 20)->nullable();
            $table->boolean('bin_mandatory')->default(false);
            $table->decimal('max_weight', 15, 4)->nullable();

            $table->boolean('blocked')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['location_id', 'zone_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
