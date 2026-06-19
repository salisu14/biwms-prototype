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
        Schema::create('asset_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('main_asset_id')->constrained('assets')->onDelete('cascade');
            $table->foreignId('component_asset_id')->constrained('assets')->onDelete('cascade');
            $table->decimal('quantity', 20, 4)->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade');
            $table->date('maintenance_date');
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');
            $table->string('service_agent_id')->nullable();
            $table->string('description');
            $table->decimal('cost', 20, 4)->default(0);
            $table->date('next_service_date')->nullable();
            $table->boolean('completed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_maintenances');
        Schema::dropIfExists('asset_components');
    }
};
