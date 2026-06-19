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
        Schema::create('maintenance_contracts', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('contract_no', 50)->unique();
            $table->string('description', 200);
            $table->string('external_reference', 100)->nullable(); // Vendor's contract number

            // Contract type and status
            $table->enum('contract_type', \App\Enums\MaintenanceContractType::cases());
            $table->enum('status', \App\Enums\MaintenanceContractStatus::cases())->default('draft');

            // Parties
            $table->foreignId('vendor_id')->constrained('vendors'); // Maintenance service provider
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees'); // Internal owner

            // Contract terms
            $table->date('start_date');
            $table->date('end_date');
            $table->date('renewal_date')->nullable(); // When to negotiate renewal
            $table->integer('notice_period_days')->default(30); // Termination notice required
            $table->boolean('auto_renewal')->default(false);
            $table->integer('auto_renewal_period_months')->nullable();

            // Billing
            $table->enum('billing_cycle', \App\Enums\MaintenanceContractBillingCycle::cases());
            $table->decimal('contract_value', 15, 4); // Total contract value
            $table->decimal('billing_amount', 15, 4); // Amount per billing cycle
            $table->string('currency_code', 10)->default('USD');
            $table->decimal('hourly_rate', 15, 4)->nullable(); // For time-based contracts
            $table->decimal('parts_discount_percent', 5, 2)->default(0); // Discount on parts

            // Coverage limits
            $table->integer('max_incidents_per_year')->nullable();
            $table->integer('max_hours_per_year')->nullable();
            $table->decimal('max_cost_per_year', 15, 4)->nullable();
            $table->decimal('deductible_amount', 15, 4)->default(0); // Per-incident deductible

            // Response time commitments
            $table->integer('response_time_hours_critical')->nullable();
            $table->integer('response_time_hours_standard')->nullable();
            $table->integer('resolution_time_hours')->nullable();

            // Covered assets (can be specific or category-based)
            $table->enum('coverage_type', ['specific_assets', 'asset_category', 'location_based', 'all_assets'])->default('specific_assets');
            $table->foreignId('fa_class_id')->nullable()->constrained('fa_classes'); // If category-based
            $table->foreignId('fa_location_id')->nullable()->constrained('fa_locations'); // If location-based

            // GL Accounts for posting
            $table->foreignId('expense_account_id')->constrained('chart_of_accounts');
            $table->foreignId('prepaid_account_id')->nullable()->constrained('chart_of_accounts'); // For prepaid contracts
            $table->foreignId('accrual_account_id')->nullable()->constrained('chart_of_accounts');

            // Dimensions
            $table->string('shortcut_dimension_1_code', 50)->nullable();
            $table->string('shortcut_dimension_2_code', 50)->nullable();
            $table->json('dimension_set_entry')->nullable();

            // Terms and conditions
            $table->text('scope_of_work')->nullable();
            $table->text('exclusions')->nullable();
            $table->text('special_terms')->nullable();
            $table->text('termination_conditions')->nullable();

            // Document storage
            $table->string('contract_document_path')->nullable();
            $table->json('attachments')->nullable();

            // Tracking
            $table->integer('total_incidents_logged')->default(0);
            $table->decimal('total_cost_incurred', 15, 4)->default(0);
            $table->timestamp('last_service_date')->nullable();
            $table->timestamp('next_scheduled_review')->nullable();

            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('modified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['vendor_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index(['contract_type', 'status']);
            $table->index(['fa_class_id', 'fa_location_id']);
        });


        // Scheduled maintenance visits (for preventive contracts)
        Schema::create('maintenance_contract_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_contract_id')->constrained('maintenance_contracts')->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->nullable()->constrained('fixed_assets')->nullOnDelete(); // Specific asset or null for all
            $table->enum('frequency', ['weekly', 'monthly', 'quarterly', 'semi_annual', 'annual', 'custom'])->default('monthly');
            $table->integer('interval_months')->default(1);
            $table->date('first_service_date');
            $table->date('last_service_date')->nullable();
            $table->date('next_service_date');
            $table->text('service_description');
            $table->decimal('estimated_cost', 15, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Billing schedule for the contract
        Schema::create('maintenance_contract_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_contract_id')->constrained('maintenance_contracts')->cascadeOnDelete();
            $table->date('billing_date');
            $table->decimal('amount', 15, 4);
            $table->enum('status', ['scheduled', 'invoiced', 'paid', 'overdue'])->default('scheduled');
            $table->foreignId('purchase_invoice_id')->nullable()->constrained('purchase_invoices')->nullOnDelete();
            $table->date('actual_invoice_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_contract_billings');
        Schema::dropIfExists('maintenance_contract_schedules');
        Schema::dropIfExists('maintenance_contracts');
    }
};
