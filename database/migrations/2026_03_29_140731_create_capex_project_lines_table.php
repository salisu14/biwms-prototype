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
        // CapEx Project Lines (detailed cost tracking)
        Schema::create('capex_project_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capex_project_id')->constrained('capex_projects')->onDelete('cascade');
            $table->integer('line_number');

            // Line classification
            $table->string('line_type'); // MATERIAL, LABOR, EXTERNAL_SERVICE, OVERHEAD, TOOLING, INTEREST, CONTINGENCY

            // Description
            $table->text('description');

            // Budget vs Actual tracking
            $table->decimal('budget_amount', 15, 2)->default(0);
            $table->decimal('committed_amount', 15, 2)->default(0); // POs placed
            $table->decimal('actual_amount', 15, 2)->default(0);
            $table->decimal('variance_amount', 15, 2)->default(0); // Calculated: actual - budget

            // Source document linking (for traceability)
            $table->string('source_document_type')->nullable(); // PURCHASE_ORDER, PRODUCTION_ORDER, JOURNAL, VENDOR_INVOICE, INTEREST_JOURNAL
            $table->unsignedBigInteger('source_document_id')->nullable();
            $table->string('source_document_no')->nullable();
            $table->date('source_document_date')->nullable();

            // Production Order integration (for assets under construction)
            $table->foreignId('production_order_id')->nullable()->constrained('production_orders');
            $table->foreignId('production_order_component_id')->nullable()->constrained('production_order_components');
            $table->foreignId('capacity_ledger_entry_id')->nullable()->constrained('capacity_ledger_entries');

            // Vendor/Procurement info
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');
            $table->string('purchase_order_number')->nullable();

            // Capitalization status
            $table->boolean('eligible_for_capitalization')->default(true);
            $table->text('non_capitalization_reason')->nullable(); // If not eligible, why?
            $table->boolean('capitalized')->default(false);
            $table->timestamp('capitalized_at')->nullable();
            $table->foreignId('capitalized_by')->nullable()->constrained('users');

            // GL posting reference
            $table->string('gl_entry_reference')->nullable();

            // Line status
            $table->string('status')->default('PLANNED'); // PLANNED, COMMITTED, RECEIVED, INVOICED, CAPITALIZED, EXPENSED

            $table->timestamps();

            $table->unique(['capex_project_id', 'line_number']);
            $table->index(['capex_project_id', 'line_type']);
            $table->index(['capex_project_id', 'status']);
            $table->index(['eligible_for_capitalization', 'capitalized']);
            $table->index(['production_order_id']);
            $table->index(['source_document_type', 'source_document_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capex_project_lines');
    }
};
