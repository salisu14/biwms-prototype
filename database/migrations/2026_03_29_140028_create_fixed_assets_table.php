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
        // Fixed Assets (create first - referenced by CapExProject)
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description');
            $table->string('asset_type')->default('MACHINERY'); // MACHINERY, BUILDING, TOOLING, VEHICLE, IT_EQUIPMENT, LAND

            // Acquisition details
            $table->date('acquisition_date')->nullable();
            $table->decimal('acquisition_cost', 15, 2)->default(0);
            $table->decimal('accumulated_depreciation', 15, 2)->default(0);
            $table->decimal('net_book_value', 15, 2)->default(0);
            $table->decimal('salvage_value', 15, 2)->default(0);

            // Depreciation settings
            $table->integer('useful_life_years')->default(5);
            $table->string('depreciation_method')->default('STRAIGHT_LINE'); // STRAIGHT_LINE, DECLINING_BALANCE, UNITS_OF_PRODUCTION, SUM_OF_YEARS
            $table->decimal('annual_depreciation_amount', 15, 2)->default(0);
            $table->decimal('depreciation_rate', 5, 2)->nullable(); // For declining balance

            // For production capacity-based depreciation
            $table->integer('annual_capacity_minutes')->nullable();
            $table->decimal('efficiency_percent', 5, 2)->default(100);

            // For building/facility assets
            $table->decimal('total_square_footage', 10, 2)->nullable();
            $table->foreignId('parent_building_id')->nullable()->constrained('fixed_assets');

            // Status
            $table->string('status')->default('ACTIVE'); // ACTIVE, DISPOSED, IDLE, UNDER_CONSTRUCTION
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_proceeds', 15, 2)->default(0);

            // GL accounts
            $table->foreignId('asset_gl_account_id')->constrained('chart_of_accounts');
            $table->foreignId('accumulated_depreciation_gl_account_id')->constrained('chart_of_accounts');
            $table->foreignId('depreciation_expense_gl_account_id')->constrained('chart_of_accounts');

            // CapEx project reference (if created from project)
//            $table->foreignId('capex_project_id')->nullable()->constrained('capex_projects');

            // Location
            $table->string('location_code')->nullable();
            $table->string('responsible_employee_id')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('last_modified_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['asset_type', 'status']);
            $table->index(['acquisition_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
