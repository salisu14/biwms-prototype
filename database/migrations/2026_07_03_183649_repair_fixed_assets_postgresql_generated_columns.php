<?php

use App\Enums\DepreciationCalculationMethod;
use App\Enums\DepreciationMethod;
use App\Enums\FAStatus;
use App\Enums\FixedAssetType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('fixed_assets')) {
            $this->dropLeftoverPostgresSequenceIfNeeded();
            $this->createFixedAssetsTable();

            return;
        }

        if ($this->isPostgresGeneratedColumn('fixed_assets', 'net_book_value')) {
            Schema::table('fixed_assets', function (Blueprint $table): void {
                $table->dropColumn('net_book_value');
            });
        }

        if (! Schema::hasColumn('fixed_assets', 'net_book_value')) {
            Schema::table('fixed_assets', function (Blueprint $table): void {
                $table->decimal('net_book_value', 15, 4)->default(0)->after('accumulated_depreciation');
            });
        }

        DB::table('fixed_assets')->update([
            'net_book_value' => DB::raw('book_value - accumulated_depreciation'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This repair migration intentionally does not restore generated columns.
    }

    private function createFixedAssetsTable(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('fa_no', 50)->unique();
            $table->string('description', 100);
            $table->string('description_2', 100)->nullable();
            $table->string('search_description', 100)->nullable();
            $table->enum('fa_type', array_column(FixedAssetType::cases(), 'value'))->default('fixed_asset');
            $table->foreignId('fa_class_id')->nullable()->constrained('fa_classes')->nullOnDelete();
            $table->foreignId('fa_subclass_id')->nullable()->constrained('fa_subclasses')->nullOnDelete();
            $table->foreignId('fa_location_id')->nullable()->constrained('fa_locations')->nullOnDelete();
            $table->foreignId('fa_posting_group_id')->constrained('fa_posting_groups');
            $table->foreignId('depreciation_book_id')->constrained('depreciation_books');
            $table->string('serial_no', 100)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->foreignId('responsible_employee_id')->nullable()->constrained('users');
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');
            $table->foreignId('main_vendor_id')->nullable()->constrained('vendors');
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->string('fa_location_code', 50)->nullable();
            $table->date('acquisition_date')->nullable();
            $table->date('depreciation_starting_date')->nullable();
            $table->date('depreciation_ending_date')->nullable();
            $table->decimal('acquisition_cost', 15, 4)->default(0);
            $table->foreignId('acquisition_vendor_id')->nullable()->constrained('vendors');
            $table->string('acquisition_invoice_no', 50)->nullable();
            $table->enum('depreciation_method', array_column(DepreciationMethod::cases(), 'value'))->default('straight_line');
            $table->decimal('depreciation_rate', 7, 4)->nullable();
            $table->integer('useful_life_years')->nullable();
            $table->integer('useful_life_months')->nullable();
            $table->decimal('salvage_value', 15, 4)->default(0);
            $table->decimal('salvage_value_percentage', 5, 2)->nullable();
            $table->decimal('total_estimated_units', 15, 4)->nullable();
            $table->decimal('units_produced_to_date', 15, 4)->default(0);
            $table->enum('declining_balance_calc', array_column(DepreciationCalculationMethod::cases(), 'value'))->nullable();
            $table->decimal('book_value', 15, 4)->default(0);
            $table->decimal('accumulated_depreciation', 15, 4)->default(0);
            $table->decimal('net_book_value', 15, 4)->default(0);
            $table->decimal('last_revaluation_amount', 15, 4)->nullable();
            $table->date('last_revaluation_date')->nullable();
            $table->decimal('revaluation_reserve', 15, 4)->default(0);
            $table->decimal('insurance_value', 15, 4)->nullable();
            $table->date('insurance_expiry_date')->nullable();
            $table->string('insurance_policy_no', 50)->nullable();
            $table->enum('status', array_column(FAStatus::cases(), 'value'))->default('new');
            $table->boolean('blocked')->default(false);
            $table->text('blocked_reason')->nullable();
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_proceeds', 15, 4)->nullable();
            $table->decimal('disposal_cost', 15, 4)->nullable();
            $table->decimal('disposal_gain_loss', 15, 4)->nullable();
            $table->string('shortcut_dimension_1_code', 50)->nullable();
            $table->string('shortcut_dimension_2_code', 50)->nullable();
            $table->json('dimension_set_entry')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('modified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fa_type', 'status']);
            $table->index(['fa_posting_group_id', 'depreciation_book_id']);
            $table->index(['acquisition_date', 'depreciation_starting_date']);
        });
    }

    private function dropLeftoverPostgresSequenceIfNeeded(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $sequenceExists = DB::selectOne(
            "select 1 from pg_class where relkind = 'S' and relname = 'fixed_assets_id_seq'"
        );

        if ($sequenceExists !== null) {
            DB::statement('drop sequence if exists fixed_assets_id_seq');
        }
    }

    private function isPostgresGeneratedColumn(string $table, string $column): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        $row = DB::selectOne(
            'select is_generated from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name = ?',
            [$table, $column]
        );

        return ($row?->is_generated ?? 'NEVER') !== 'NEVER';
    }
};
