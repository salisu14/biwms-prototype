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
        Schema::create('production_journal_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique(); // "PROD-CONS", "PROD-OUTPUT", "CAPACITY"
            $table->string('description', 100)->nullable();
            $table->enum('journal_type', ['consumption', 'output', 'capacity'])->default('consumption');
            $table->foreignId('number_series_id')->constrained('number_series');
            $table->foreignId('posting_number_series_id')->nullable()->constrained('number_series');
            $table->string('source_code', 20)->nullable(); // "PRODJNL"

            // Production-specific controls
            $table->enum('flushing_method_filter', ['manual', 'forward', 'backward', 'pick', 'consume', 'all'])->default('all');
            $table->boolean('allow_flushing_override')->default(false);
            $table->boolean('auto_post_output')->default(false); // Auto-post when operation completes
            $table->boolean('auto_post_consumption')->default(false);

            // Capacity posting
            $table->boolean('post_capacity')->default(true);
            $table->boolean('post_time')->default(true);
            $table->boolean('post_quantity')->default(false);
            $table->boolean('absorb_overhead')->default(true);
            $table->enum('overhead_rate_source', ['work_center', 'machine_center', 'routing'])->default('work_center');

            // Account determination
            $table->foreignId('default_wip_account_id')->nullable()->constrained('chart_of_accounts');
            $table->boolean('force_wip_account')->default(false);
            $table->boolean('use_production_order_account_setup')->default(true);

            // Dimension control
            $table->json('mandatory_dimensions')->nullable();
            $table->json('default_dimensions')->nullable();
            $table->boolean('copy_from_production_order')->default(true);

            $table->boolean('consolidate_lines')->default(true); // Combine same item/lot lines
            $table->boolean('test_report_before_posting')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('production_journal_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('production_journal_templates')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('description', 100)->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'released', 'posted', 'cancelled'])->default('open');
            $table->foreignId('production_order_id')->nullable()->constrained('production_orders'); // Dedicated batch for single order
            $table->json('dimension_filter')->nullable();
            $table->string('reason_code', 20)->nullable();
            $table->boolean('auto_post_on_release')->default(false);
            $table->timestamps();

            $table->unique(['template_id', 'name']);
        });

        Schema::create('production_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('production_journal_batches')->cascadeOnDelete();
            $table->integer('line_no')->default(10000);
            $table->date('posting_date');
            $table->enum('entry_type', ['consumption', 'output', 'capacity', 'scrap']);

            // Production Order reference (mandatory)
            $table->foreignId('production_order_id')->constrained('production_orders');
            $table->string('production_order_no', 50); // Denormalized
            $table->integer('routing_line_no')->nullable();
            $table->foreignId('routing_line_id')->nullable()->constrained('production_order_routing_lines');

            // For consumption/output
            $table->foreignId('item_id')->nullable()->constrained('items');
            $table->string('item_no', 50)->nullable();
            $table->string('description', 100)->nullable();
            $table->string('unit_of_measure_code', 20)->nullable();
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_base', 15, 4);

            // Location and tracking
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->foreignId('zone_id')->nullable()->constrained('zones');
            $table->foreignId('bin_id')->nullable()->constrained('bins');
            $table->string('lot_no', 50)->nullable();
            $table->string('serial_no', 50)->nullable();
            $table->date('expiration_date')->nullable();

            // For output: destination
            $table->foreignId('output_location_id')->nullable()->constrained('locations');
            $table->foreignId('output_bin_id')->nullable()->constrained('bins');

            // For capacity entries
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers');
            $table->foreignId('machine_center_id')->nullable()->constrained('machine_centers');
            $table->decimal('setup_time', 10, 4)->nullable(); // Hours
            $table->decimal('run_time', 10, 4)->nullable(); // Hours
            $table->decimal('stop_time', 10, 4)->nullable(); // Hours
            $table->integer('output_quantity')->nullable(); // Pieces produced
            $table->integer('scrap_quantity')->nullable();

            // Costing
            $table->decimal('direct_cost', 15, 4)->nullable();
            $table->decimal('overhead_cost', 15, 4)->nullable();
            $table->decimal('total_cost', 15, 4)->nullable();
            $table->decimal('unit_cost', 15, 4)->nullable();

            // Flushing control
            $table->enum('flushing_method', ['manual', 'forward', 'backward', 'pick', 'consume'])->nullable();
            $table->boolean('flushed')->default(false);
            $table->timestamp('flushed_at')->nullable();

            // Accounts
            $table->foreignId('wip_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('inventory_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('direct_cost_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('overhead_account_id')->nullable()->constrained('chart_of_accounts');

            // Dimensions
            $table->string('shortcut_dimension_1_code', 50)->nullable();
            $table->string('shortcut_dimension_2_code', 50)->nullable();
            $table->json('dimension_set_entry')->nullable();

            $table->string('source_code', 20)->nullable();
            $table->string('reason_code', 20)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at');

            // Posting tracking
            $table->enum('line_status', ['open', 'checked', 'rejected', 'posted'])->default('open');
            $table->foreignId('item_ledger_entry_id')->nullable()->constrained('item_ledger_entries');
            $table->foreignId('capacity_ledger_entry_id')->nullable()->constrained('capacity_ledger_entries');

            $table->unique(['batch_id', 'line_no']);
            $table->index(['production_order_id', 'entry_type', 'line_status']);
            $table->index(['posting_date', 'work_center_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_journal_lines');
        Schema::dropIfExists('production_journal_batches');
        Schema::dropIfExists('production_journal_templates');
    }
};
