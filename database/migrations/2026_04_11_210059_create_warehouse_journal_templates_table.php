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
        Schema::create('warehouse_journal_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique(); // "WH-PICK", "WH-PUTAWAY", "WH-MOVEMENT"
            $table->string('description', 100)->nullable();
            $table->enum('journal_type', ['pick', 'put_away', 'movement', 'physical_inventory', 'adjustment'])->default('movement');
            $table->foreignId('number_series_id')->constrained('number_series');
            $table->string('source_code', 20)->nullable(); // "WHJNL"

            // Warehouse controls
            $table->boolean('zone_mandatory')->default(false);
            $table->boolean('bin_mandatory')->default(true);
            $table->boolean('item_tracking_mandatory')->default(false);
            $table->boolean('directed_put_away_and_pick')->default(false); // Advanced WMS
            $table->boolean('require_warehouse_activity')->default(false); // Must come from pick/put-away worksheet

            // Physical inventory
            $table->boolean('is_physical_inventory')->default(false);
            $table->boolean('calculate_inventory')->default(false); // Auto-calculate expected qty
            $table->boolean('items_not_on_inventory')->default(false); // Allow zero-expected items

            // Adjustment controls
            $table->boolean('require_reason_code')->default(true);
            $table->json('allowed_reason_codes')->nullable();
            $table->foreignId('default_adjustment_account_id')->nullable()->constrained('chart_of_accounts');

            // Dimension control
            $table->json('mandatory_dimensions')->nullable();
            $table->json('default_dimensions')->nullable();

            $table->boolean('test_report_before_posting')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('warehouse_journal_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('warehouse_journal_templates')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('description', 100)->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'released', 'posted', 'cancelled'])->default('open');
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('zone_id')->nullable()->constrained('zones');
            $table->enum('journal_type', ['pick', 'put_away', 'movement', 'physical_inventory', 'adjustment'])->nullable();
            $table->json('dimension_filter')->nullable();
            $table->string('reason_code', 20)->nullable();
            $table->boolean('copy_from_warehouse_activity')->default(true);
            $table->timestamps();

            $table->unique(['template_id', 'name']);
        });

        Schema::create('warehouse_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('warehouse_journal_batches')->cascadeOnDelete();
            $table->integer('line_no')->default(10000);
            $table->date('posting_date');
            $table->enum('entry_type', ['pick', 'put_away', 'movement', 'positive_adj', 'negative_adj', 'physical_inventory']);

            // Document references
            $table->string('document_no', 50)->nullable();
            $table->foreignId('warehouse_activity_id')->nullable()->constrained('warehouse_activities');
            $table->foreignId('warehouse_activity_line_id')->nullable()->constrained('warehouse_activity_lines');

            // Item information
            $table->foreignId('item_id')->constrained('items');
            $table->string('item_no', 50);
            $table->string('description', 100)->nullable();
            $table->string('unit_of_measure_code', 20);
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_base', 15, 4);

            // For physical inventory
            $table->decimal('qty_calculated', 15, 4)->nullable(); // System-expected quantity
            $table->decimal('qty_physical', 15, 4)->nullable(); // User-entered count

            // Source location (Take From)
            $table->foreignId('source_location_id')->constrained('locations');
            $table->foreignId('source_zone_id')->nullable()->constrained('zones');
            $table->foreignId('source_bin_id')->nullable()->constrained('bins');
            $table->string('source_lot_no', 50)->nullable();
            $table->string('source_serial_no', 50)->nullable();

            // Destination location (Place To)
            $table->foreignId('destination_location_id')->nullable()->constrained('locations');
            $table->foreignId('destination_zone_id')->nullable()->constrained('zones');
            $table->foreignId('destination_bin_id')->nullable()->constrained('bins');
            $table->string('destination_lot_no', 50)->nullable();
            $table->string('destination_serial_no', 50)->nullable();

            // For movements/adjustments within same location
            $table->foreignId('zone_id')->nullable()->constrained('zones'); // Shortcut for single-zone entries
            $table->foreignId('bin_id')->nullable()->constrained('bins');

            // Tracking
            $table->string('lot_no', 50)->nullable();
            $table->string('serial_no', 50)->nullable();
            $table->date('expiration_date')->nullable();

            // Reason and control
            $table->string('reason_code', 20)->nullable();
            $table->text('comment')->nullable();
            $table->boolean('phys_inventory')->default(false); // From physical inventory counting

            // Dimensions
            $table->string('shortcut_dimension_1_code', 50)->nullable();
            $table->string('shortcut_dimension_2_code', 50)->nullable();
            $table->json('dimension_set_entry')->nullable();

            $table->string('source_code', 20)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at');

            // Posting tracking
            $table->enum('line_status', ['open', 'checked', 'rejected', 'posted'])->default('open');
            $table->foreignId('warehouse_entry_id')->nullable()->constrained('warehouse_entries');

            $table->unique(['batch_id', 'line_no']);
            $table->index(['posting_date', 'item_id', 'source_location_id']);
            $table->index(['warehouse_activity_id', 'warehouse_activity_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_journal_lines');
        Schema::dropIfExists('warehouse_journal_batches');
        Schema::dropIfExists('warehouse_journal_templates');
    }
};
