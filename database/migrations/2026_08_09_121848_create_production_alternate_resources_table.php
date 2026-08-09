<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_alternate_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('primary_work_center_id')->nullable()->constrained('work_centers')->cascadeOnDelete();
            $table->foreignId('primary_machine_center_id')->nullable()->constrained('machine_centers')->cascadeOnDelete();
            $table->foreignId('alternate_work_center_id')->nullable()->constrained('work_centers')->restrictOnDelete();
            $table->foreignId('alternate_machine_center_id')->nullable()->constrained('machine_centers')->restrictOnDelete();
            $table->unsignedInteger('priority')->default(100);
            $table->decimal('efficiency_factor', 8, 4)->default(1);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['primary_work_center_id', 'is_active', 'priority'], 'production_alternate_resources_wc_idx');
            $table->index(['primary_machine_center_id', 'is_active', 'priority'], 'production_alternate_resources_mc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_alternate_resources');
    }
};
