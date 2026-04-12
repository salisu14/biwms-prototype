<?php

use App\Enums\SourceCode;
use App\Enums\TemplateType;
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
        //        Schema::create('item_journal_templates', function (Blueprint $table) {
        //            $table->id();
        //            $table->string('name', 20)->unique();
        //            $table->string('description');
        //            $table->enum('template_type', array_column(TemplateType::cases(), 'value'));
        //
        //            $table->enum('source_code', array_column(SourceCode::cases(), 'value'));
        //
        //            $table->string('reason_code', 20)->nullable();
        //            $table->boolean('recurring')->default(false);
        //            $table->boolean('blocked')->default(false);
        //            $table->timestamps();
        //        });
        Schema::create('item_journal_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique(); // e.g., "ITEM", "ADJUST", "TRANSFER"
            $table->string('description', 100)->nullable();
            $table->enum('default_entry_type', ['positive_adj', 'negative_adj', 'purchase', 'sale', 'transfer', 'consumption', 'output'])->nullable();
            $table->foreignId('number_series_id')->constrained('number_series');
            $table->foreignId('posting_number_series_id')->nullable()->constrained('number_series');
            $table->string('source_code', 20)->nullable(); // "ITEMJNL"
            $table->string('reason_code', 20)->nullable();

            // Inventory posting setup
            $table->foreignId('default_inventory_account_id')->nullable()->constrained('chart_of_accounts');
            $table->boolean('force_inventory_account')->default(false);
            $table->boolean('item_tracking_mandatory')->default(false);
            $table->boolean('lot_mandatory')->default(false);
            $table->boolean('serial_no_mandatory')->default(false);
            $table->boolean('expiration_date_mandatory')->default(false);
            $table->boolean('warehouse_location_mandatory')->default(false);
            $table->boolean('bin_mandatory')->default(false);
            $table->boolean('check_warehouse_availability')->default(true);
            $table->boolean('allow_negative_inventory')->default(false);
            $table->boolean('costing_per_entry')->default(false); // Override item costing method

            // Dimension control
            $table->json('mandatory_dimensions')->nullable();
            $table->json('default_dimensions')->nullable();

            // Item filtering
            $table->json('allowed_item_categories')->nullable();
            $table->json('blocked_item_nos')->nullable();

            $table->boolean('test_report_before_posting')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('item_journal_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('item_journal_templates')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('description', 100)->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'released', 'posted', 'cancelled'])->default('open');
            $table->foreignId('location_id')->nullable()->constrained('location_masters'); // Default location
            $table->enum('default_entry_type', ['positive_adj', 'negative_adj', 'purchase', 'sale', 'transfer', 'consumption', 'output'])->nullable();
            $table->json('dimension_filter')->nullable();
            $table->string('reason_code', 20)->nullable();
            $table->boolean('copy_item_dimensions')->default(true);
            $table->timestamps();

            $table->unique(['template_id', 'name']);
        });

        Schema::create('item_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('item_journal_batches')->cascadeOnDelete();
            $table->integer('line_no')->default(10000);
            $table->date('posting_date');
            $table->enum('entry_type', ['positive_adjustment', 'negative_adjustment', 'purchase', 'sale', 'transfer', 'consumption', 'output', 'assembly_consumption', 'assembly_output']);

            // Document references
            $table->string('document_type', 30)->nullable();
            $table->string('document_no', 50)->nullable();
            $table->string('external_document_no', 50)->nullable();

            // Item information
            $table->foreignId('item_id')->constrained('items');
            $table->string('item_no', 50); // Denormalized for performance
            $table->string('description', 100)->nullable();
            $table->string('description_2', 100)->nullable();
            $table->string('unit_of_measure_code', 20);
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_base', 15, 4); // In base UOM

            // Location and Tracking
            $table->foreignId('location_id')->constrained('location_masters');
            $table->foreignId('zone_id')->nullable()->constrained('warehouse_zones');
            $table->foreignId('bin_id')->nullable()->constrained('bins');
            $table->string('lot_no', 50)->nullable();
            $table->string('serial_no', 50)->nullable();
            $table->date('expiration_date')->nullable();
            $table->date('warranty_date')->nullable();

            // For transfers
            $table->foreignId('new_location_id')->nullable()->constrained('location_masters');
            $table->foreignId('new_zone_id')->nullable()->constrained('warehouse_zones');
            $table->foreignId('new_bin_id')->nullable()->constrained('bins');
            $table->string('new_lot_no', 50)->nullable();

            // Valuation
            $table->decimal('unit_amount', 15, 4)->nullable(); // User-entered cost
            $table->decimal('unit_cost', 15, 4)->nullable(); // Calculated/actual cost
            $table->decimal('amount', 15, 4)->nullable(); // quantity * unit_cost
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->string('currency_code', 10)->nullable();
            $table->decimal('amount_lcy', 15, 4)->nullable();

            // Accounts (determined by posting setup, but overridable)
            $table->foreignId('inventory_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('offset_account_id')->nullable()->constrained('chart_of_accounts'); // Balancing account

            // Dimensions
            $table->string('shortcut_dimension_1_code', 50)->nullable();
            $table->string('shortcut_dimension_2_code', 50)->nullable();
            $table->json('dimension_set_entry')->nullable();

            // Source tracking
            $table->string('source_code', 20)->nullable();
            $table->string('reason_code', 20)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at');

            // Production/Warehouse integration
            $table->foreignId('production_order_id')->nullable()->constrained('production_orders');
            $table->integer('production_order_line_no')->nullable();
            $table->foreignId('warehouse_activity_id')->nullable()->constrained('warehouse_activities');

            // Posting status
            $table->enum('line_status', ['open', 'checked', 'rejected', 'posted'])->default('open');
            $table->foreignId('item_ledger_entry_id')->nullable()->constrained('item_ledger_entries');
            $table->foreignId('value_entry_id')->nullable()->constrained('value_entries');

            $table->unique(['batch_id', 'line_no']);
            $table->index(['posting_date', 'item_id', 'location_id']);
            $table->index(['production_order_id', 'production_order_line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_journal_lines');
        Schema::dropIfExists('item_journal_batches');
        Schema::dropIfExists('item_journal_templates');
    }
};
