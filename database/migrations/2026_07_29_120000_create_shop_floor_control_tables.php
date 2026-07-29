<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_operation_executions')) {
            Schema::create('production_operation_executions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                $table->foreignId('production_order_id')->constrained('production_orders')->restrictOnDelete();
                $table->foreignId('routing_line_id')->constrained('production_order_routing_lines')->restrictOnDelete();
                $table->integer('operation_no')->nullable();
                $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
                $table->foreignId('machine_center_id')->nullable()->constrained('machine_centers')->nullOnDelete();
                $table->foreignId('operator_employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->foreignId('operator_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('shift_id')->nullable()->constrained('employee_shifts')->nullOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->string('status', 40)->default('not_started');
                $table->decimal('planned_quantity', 18, 8)->default(0);
                $table->decimal('good_quantity', 18, 8)->default(0);
                $table->decimal('scrap_quantity', 18, 8)->default(0);
                $table->decimal('rework_quantity', 18, 8)->default(0);
                $table->unsignedBigInteger('setup_seconds')->default(0);
                $table->unsignedBigInteger('run_seconds')->default(0);
                $table->unsignedBigInteger('labour_seconds')->default(0);
                $table->unsignedBigInteger('machine_seconds')->default(0);
                $table->unsignedBigInteger('downtime_seconds')->default(0);
                $table->date('execution_date')->nullable();
                $table->date('posting_date')->nullable();
                $table->string('source_device', 80)->nullable();
                $table->string('idempotency_key', 160)->nullable()->unique();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reversed_at')->nullable();
                $table->string('reason_code', 50)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('original_execution_id')->nullable()->constrained('production_operation_executions')->nullOnDelete();
                $table->foreignId('reversal_execution_id')->nullable()->constrained('production_operation_executions')->nullOnDelete();
                $table->foreignId('production_journal_batch_id')->nullable()->constrained('production_journal_batches')->nullOnDelete();
                $table->timestamps();

                $table->unique(['production_order_id', 'routing_line_id', 'original_execution_id'], 'production_execution_replacement_unique');
                $table->index(['status', 'work_center_id', 'machine_center_id'], 'production_execution_queue_idx');
                $table->index(['production_order_id', 'status'], 'production_execution_order_status_idx');
                $table->index(['operator_employee_id', 'status'], 'production_execution_operator_status_idx');
            });
        }

        if (! Schema::hasTable('production_operator_assignments')) {
            Schema::create('production_operator_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('production_operation_execution_id')->constrained('production_operation_executions')->restrictOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 40)->default('assigned');
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['production_operation_execution_id', 'employee_id'], 'production_operator_assignment_unique');
                $table->index(['employee_id', 'status']);
            });
        }

        if (! Schema::hasTable('production_operation_execution_events')) {
            Schema::create('production_operation_execution_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('production_operation_execution_id')->constrained('production_operation_executions')->restrictOnDelete();
                $table->string('event_type', 60);
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40)->nullable();
                $table->timestamp('occurred_at');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->string('idempotency_key', 160)->nullable()->unique();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['production_operation_execution_id', 'event_type'], 'production_execution_event_type_idx');
            });
        }

        if (! Schema::hasTable('production_time_entries')) {
            Schema::create('production_time_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('production_operation_execution_id')->constrained('production_operation_executions')->restrictOnDelete();
                $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->foreignId('machine_center_id')->nullable()->constrained('machine_centers')->nullOnDelete();
                $table->string('time_type', 30);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->unsignedBigInteger('duration_seconds')->default(0);
                $table->boolean('manual')->default(false);
                $table->boolean('exclusive_machine')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('idempotency_key', 160)->nullable()->unique();
                $table->text('reason')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'started_at', 'ended_at'], 'production_time_employee_overlap_idx');
                $table->index(['machine_center_id', 'started_at', 'ended_at'], 'production_time_machine_overlap_idx');
            });
        }

        $this->createReasonAndOperationalTables();
        $this->addProductionJournalExecutionLink();
    }

    private function createReasonAndOperationalTables(): void
    {
        if (! Schema::hasTable('production_scrap_reasons')) {
            Schema::create('production_scrap_reasons', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('stage', 40)->default('process');
                $table->string('default_posting_treatment', 50)->default('operational_only');
                $table->boolean('requires_approval')->default(false);
                $table->boolean('requires_quality_review')->default(false);
                $table->boolean('recoverable')->default(false);
                $table->boolean('reworkable')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_downtime_reasons')) {
            Schema::create('production_downtime_reasons', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('category', 40)->default('unplanned');
                $table->boolean('requires_approval')->default(false);
                $table->boolean('blocks_completion')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_scrap_entries')) {
            Schema::create('production_scrap_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('production_operation_execution_id')->constrained('production_operation_executions')->restrictOnDelete();
                $table->foreignId('production_scrap_reason_id')->constrained('production_scrap_reasons')->restrictOnDelete();
                $table->string('stage', 40);
                $table->string('posting_treatment', 50);
                $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
                $table->decimal('quantity', 18, 8);
                $table->string('unit_of_measure_code', 20)->nullable();
                $table->boolean('requires_approval')->default(false);
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->string('idempotency_key', 160)->nullable()->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_downtime_entries')) {
            Schema::create('production_downtime_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('production_operation_execution_id')->constrained('production_operation_executions')->restrictOnDelete();
                $table->foreignId('production_downtime_reason_id')->nullable()->constrained('production_downtime_reasons')->nullOnDelete();
                $table->string('category', 40)->default('unplanned');
                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();
                $table->unsignedBigInteger('duration_seconds')->default(0);
                $table->boolean('planned')->default(false);
                $table->boolean('requires_approval')->default(false);
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->string('idempotency_key', 160)->nullable()->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_rework_entries')) {
            Schema::create('production_rework_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('production_operation_execution_id')->constrained('production_operation_executions')->restrictOnDelete();
                $table->string('status', 40)->default('identified');
                $table->decimal('quantity', 18, 8)->default(0);
                $table->string('unit_of_measure_code', 20)->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('reason')->nullable();
                $table->text('notes')->nullable();
                $table->string('idempotency_key', 160)->nullable()->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_quality_checks')) {
            Schema::create('production_quality_checks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('production_operation_execution_id')->constrained('production_operation_executions')->restrictOnDelete();
                $table->string('stage', 40);
                $table->string('result', 40)->default('pending');
                $table->string('disposition', 40)->nullable();
                $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('checked_at')->nullable();
                $table->json('measurements')->nullable();
                $table->text('notes')->nullable();
                $table->string('idempotency_key', 160)->nullable()->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_quality_holds')) {
            Schema::create('production_quality_holds', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('production_operation_execution_id')->constrained('production_operation_executions')->restrictOnDelete();
                $table->string('status', 30)->default('active');
                $table->text('reason');
                $table->foreignId('placed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('placed_at')->nullable();
                $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('released_at')->nullable();
                $table->text('release_reason')->nullable();
                $table->timestamps();

                $table->index(['status', 'production_operation_execution_id'], 'production_quality_hold_status_idx');
            });
        }

        if (! Schema::hasTable('production_operation_notes')) {
            Schema::create('production_operation_notes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('production_operation_execution_id')->constrained('production_operation_executions')->restrictOnDelete();
                $table->string('category', 40)->default('general');
                $table->text('body');
                $table->string('attachment_path')->nullable();
                $table->boolean('private')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_shift_handovers')) {
            Schema::create('production_shift_handovers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('production_operation_execution_id')->constrained('production_operation_executions')->restrictOnDelete();
                $table->foreignId('from_employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->foreignId('to_employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->timestamp('handed_over_at');
                $table->text('summary');
                $table->json('open_items')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    private function addProductionJournalExecutionLink(): void
    {
        if (! Schema::hasTable('production_journal_lines') || Schema::hasColumn('production_journal_lines', 'production_operation_execution_id')) {
            return;
        }

        Schema::table('production_journal_lines', function (Blueprint $table): void {
            $table->foreignId('production_operation_execution_id')
                ->nullable()
                ->after('routing_line_id')
                ->constrained('production_operation_executions')
                ->nullOnDelete();
            $table->string('shop_floor_idempotency_key', 160)->nullable()->unique();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('production_journal_lines') && Schema::hasColumn('production_journal_lines', 'production_operation_execution_id')) {
            Schema::table('production_journal_lines', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('production_operation_execution_id');
                $table->dropColumn('shop_floor_idempotency_key');
            });
        }

        Schema::dropIfExists('production_shift_handovers');
        Schema::dropIfExists('production_operation_notes');
        Schema::dropIfExists('production_quality_holds');
        Schema::dropIfExists('production_quality_checks');
        Schema::dropIfExists('production_rework_entries');
        Schema::dropIfExists('production_downtime_entries');
        Schema::dropIfExists('production_scrap_entries');
        Schema::dropIfExists('production_downtime_reasons');
        Schema::dropIfExists('production_scrap_reasons');
        Schema::dropIfExists('production_time_entries');
        Schema::dropIfExists('production_operation_execution_events');
        Schema::dropIfExists('production_operator_assignments');
        Schema::dropIfExists('production_operation_executions');
    }
};
