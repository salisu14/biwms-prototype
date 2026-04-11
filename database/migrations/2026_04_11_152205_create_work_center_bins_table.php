<?php

use App\Enums\FlushingMethod;
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
        Schema::create('work_center_bins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_center_id')->constrained('work_centers')->cascadeOnDelete();
            $table->foreignId('open_shop_floor_bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->foreignId('to_production_bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->foreignId('from_production_bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->foreignId('fixed_bin_id')->nullable()->constrained('bins')->nullOnDelete(); // Default bin for this work center
            $table->enum('flushing_method', FlushingMethod::cases())->default('manual');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_center_bins');
    }
};
