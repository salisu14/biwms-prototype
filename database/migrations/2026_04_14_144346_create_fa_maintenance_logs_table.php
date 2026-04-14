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
        // FA Maintenance/Service Log
        Schema::create('fa_maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->date('service_date');
            $table->enum('service_type', ['preventive', 'corrective', 'upgrade', 'inspection']);
            $table->text('description');
            $table->decimal('cost', 15, 4)->default(0);
            $table->boolean('capitalized')->default(false); // If true, added to asset cost
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');
            $table->foreignId('maintenance_contract_id')->nullable()->constrained('maintenance_contracts');
            $table->date('next_service_date')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fa_maintenance_logs');
    }
};
