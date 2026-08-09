<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_schedule_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_schedule_id')->constrained('production_schedules')->cascadeOnDelete();
            $table->foreignId('production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('production_hierarchy_id')->nullable()->constrained('production_hierarchies')->nullOnDelete();
            $table->foreignId('root_production_order_id')->nullable()->constrained('production_orders')->restrictOnDelete();
            $table->unsignedInteger('line_number')->default(10000);
            $table->integer('priority')->default(100);
            $table->date('due_date')->nullable();
            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_finish_at')->nullable();
            $table->decimal('quantity_base', 18, 8)->default(0);
            $table->string('status', 40)->default('planned');
            $table->boolean('late')->default(false);
            $table->unsignedInteger('lateness_minutes')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['production_schedule_id', 'production_order_id'], 'production_schedule_lines_order_unique');
            $table->index(['production_schedule_id', 'line_number'], 'production_schedule_lines_schedule_line_idx');
            $table->index(['production_order_id', 'status'], 'production_schedule_lines_order_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_schedule_lines');
    }
};
