<?php

declare(strict_types=1);

use App\Enums\ProductionScheduleStatus;
use App\Enums\ProductionSchedulingMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('schedule_no')->unique();
            $table->string('name')->nullable();
            $table->timestamp('horizon_start_at');
            $table->timestamp('horizon_end_at');
            $table->string('status', 40)->default(ProductionScheduleStatus::Draft->value);
            $table->string('scheduling_mode', 40)->default(ProductionSchedulingMode::Forward->value);
            $table->unsignedInteger('planning_version')->default(1);
            $table->unsignedInteger('freeze_horizon_minutes')->default(480);
            $table->foreignId('supersedes_schedule_id')->nullable()->constrained('production_schedules')->nullOnDelete();
            $table->foreignId('superseded_by_schedule_id')->nullable()->constrained('production_schedules')->nullOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status'], 'production_schedules_business_status_idx');
            $table->index(['location_id', 'horizon_start_at', 'horizon_end_at'], 'production_schedules_location_horizon_idx');
            $table->index(['status', 'scheduling_mode'], 'production_schedules_status_mode_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_schedules');
    }
};
