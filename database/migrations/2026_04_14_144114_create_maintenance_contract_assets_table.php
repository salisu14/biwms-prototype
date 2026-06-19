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
        // Junction table for specific assets covered (many-to-many)
        Schema::create('maintenance_contract_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_contract_id')->constrained('maintenance_contracts')->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->string('covered_serial_no', 100)->nullable(); // Specific unit serial
            $table->text('special_conditions')->nullable(); // Asset-specific terms
            $table->decimal('asset_specific_limit', 15, 4)->nullable(); // Max cost for this asset
            $table->timestamps();

            $table->unique(['maintenance_contract_id', 'fixed_asset_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_contract_assets');
    }
};
