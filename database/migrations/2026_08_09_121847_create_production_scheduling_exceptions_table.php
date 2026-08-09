<?php

declare(strict_types=1);

use App\Enums\ProductionSchedulingExceptionSeverity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_scheduling_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_schedule_id')->constrained('production_schedules')->cascadeOnDelete();
            $table->foreignId('production_operation_schedule_id')->nullable()->constrained('production_operation_schedules')->cascadeOnDelete();
            $table->foreignId('production_order_id')->nullable()->constrained('production_orders')->nullOnDelete();
            $table->foreignId('production_order_routing_line_id')->nullable()->constrained('production_order_routing_lines')->nullOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->foreignId('machine_center_id')->nullable()->constrained('machine_centers')->nullOnDelete();
            $table->string('exception_type', 80);
            $table->string('severity', 30)->default(ProductionSchedulingExceptionSeverity::Warning->value);
            $table->string('status', 30)->default('open');
            $table->text('message');
            $table->text('suggested_action')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['production_schedule_id', 'severity', 'status'], 'production_scheduling_exceptions_schedule_status_idx');
            $table->index(['exception_type', 'status'], 'production_scheduling_exceptions_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_scheduling_exceptions');
    }
};
