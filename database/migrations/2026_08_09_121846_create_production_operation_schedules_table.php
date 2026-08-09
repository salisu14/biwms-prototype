<?php

declare(strict_types=1);

use App\Enums\ProductionOperationScheduleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_operation_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_schedule_id')->constrained('production_schedules')->cascadeOnDelete();
            $table->foreignId('production_schedule_line_id')->nullable()->constrained('production_schedule_lines')->cascadeOnDelete();
            $table->foreignId('production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('production_order_routing_line_id')->constrained('production_order_routing_lines')->restrictOnDelete();
            $table->foreignId('production_hierarchy_id')->nullable()->constrained('production_hierarchies')->nullOnDelete();
            $table->foreignId('root_production_order_id')->nullable()->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->foreignId('machine_center_id')->nullable()->constrained('machine_centers')->nullOnDelete();
            $table->foreignId('predecessor_operation_schedule_id')->nullable()->constrained('production_operation_schedules')->nullOnDelete();
            $table->foreignId('production_operation_dependency_id')->nullable()->constrained('production_operation_dependencies')->nullOnDelete();
            $table->timestamp('scheduled_start_at');
            $table->timestamp('scheduled_finish_at');
            $table->decimal('setup_duration_minutes', 18, 4)->default(0);
            $table->decimal('run_duration_minutes', 18, 4)->default(0);
            $table->decimal('wait_duration_minutes', 18, 4)->default(0);
            $table->decimal('queue_duration_minutes', 18, 4)->default(0);
            $table->decimal('quantity_base', 18, 8)->default(0);
            $table->decimal('capacity_required_minutes', 18, 4)->default(0);
            $table->unsignedInteger('sequence')->default(10000);
            $table->integer('priority')->default(100);
            $table->string('status', 40)->default(ProductionOperationScheduleStatus::Planned->value);
            $table->string('planning_source', 80)->default('aps_lite');
            $table->boolean('uses_alternate_resource')->default(false);
            $table->boolean('frozen')->default(false);
            $table->boolean('late')->default(false);
            $table->unsignedInteger('lateness_minutes')->default(0);
            $table->string('exception_state', 40)->nullable();
            $table->string('idempotency_key')->unique();
            $table->json('assignment_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['production_schedule_id', 'sequence'], 'production_operation_schedules_schedule_sequence_idx');
            $table->index(['production_order_id', 'status'], 'production_operation_schedules_order_status_idx');
            $table->index(['production_order_routing_line_id', 'production_schedule_id'], 'production_operation_schedules_routing_schedule_idx');
            $table->index(['work_center_id', 'scheduled_start_at', 'scheduled_finish_at'], 'production_operation_schedules_wc_time_idx');
            $table->index(['machine_center_id', 'scheduled_start_at', 'scheduled_finish_at'], 'production_operation_schedules_mc_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_operation_schedules');
    }
};
