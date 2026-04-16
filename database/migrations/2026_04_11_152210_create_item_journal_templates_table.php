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
            $table->foreignId('location_id')->nullable()->constrained('locations'); // Default location
            $table->enum('default_entry_type', ['positive_adj', 'negative_adj', 'purchase', 'sale', 'transfer', 'consumption', 'output'])->nullable();
            $table->json('dimension_filter')->nullable();
            $table->string('reason_code', 20)->nullable();
            $table->boolean('copy_item_dimensions')->default(true);
            $table->timestamps();

            $table->unique(['template_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_journal_lines');
        Schema::dropIfExists('item_journal_batches');
        Schema::dropIfExists('item_journal_templates');
    }
};
