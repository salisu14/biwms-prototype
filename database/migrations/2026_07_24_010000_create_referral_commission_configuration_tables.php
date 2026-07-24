<?php

declare(strict_types=1);

use App\Enums\ReferralCommissionAssignmentStatus;
use App\Enums\ReferralCommissionBasis;
use App\Enums\ReferralCommissionMethod;
use App\Enums\ReferralCommissionPlanStatus;
use App\Enums\ReferralCommissionScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_commission_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 30)->default(ReferralCommissionPlanStatus::DRAFT->value);
            $table->string('commission_basis', 30)->default(ReferralCommissionBasis::POSTED_SALES->value);
            $table->string('commission_method', 40)->default(ReferralCommissionMethod::PERCENTAGE->value);
            $table->string('commission_scope', 50)->default(ReferralCommissionScope::ALL_ELIGIBLE_SALES->value);
            $table->string('tier_basis', 40)->nullable();
            $table->decimal('percentage_rate', 9, 4)->nullable();
            $table->decimal('fixed_amount', 18, 4)->nullable();
            $table->string('fixed_amount_application', 40)->nullable();
            $table->decimal('minimum_eligible_amount', 18, 4)->nullable();
            $table->decimal('maximum_commission_amount', 18, 4)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->integer('priority')->default(100);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('inactivated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inactivated_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'code']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'is_default']);
            $table->index(['effective_from', 'effective_to']);
        });

        Schema::create('referral_commission_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('default_commission_basis', 30)->default(ReferralCommissionBasis::POSTED_SALES->value);
            $table->foreignId('default_plan_id')->nullable()->constrained('referral_commission_plans')->nullOnDelete();
            $table->boolean('require_plan_assignment')->default(true);
            $table->boolean('include_tax_in_commission_base')->default(false);
            $table->boolean('include_shipping_in_commission_base')->default(false);
            $table->boolean('deduct_line_discounts')->default(true);
            $table->boolean('deduct_invoice_discounts')->default(true);
            $table->boolean('allow_commission_on_zero_value_lines')->default(false);
            $table->boolean('allow_commission_on_free_items')->default(false);
            $table->boolean('allow_commission_for_inactive_referrer')->default(false);
            $table->foreignId('commission_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('minimum_eligible_sale_amount', 18, 4)->nullable();
            $table->unsignedSmallInteger('commission_decimal_places')->default(4);
            $table->string('rounding_mode')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('business_id');
        });

        Schema::create('referral_commission_plan_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referral_commission_plan_id')->constrained('referral_commission_plans')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->decimal('minimum_threshold', 18, 4);
            $table->decimal('maximum_threshold', 18, 4)->nullable();
            $table->decimal('percentage_rate', 9, 4)->nullable();
            $table->decimal('fixed_amount', 18, 4)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['referral_commission_plan_id', 'sequence']);
            $table->index(['referral_commission_plan_id', 'minimum_threshold', 'maximum_threshold']);
        });

        Schema::create('referral_commission_plan_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referral_commission_plan_id')->constrained('referral_commission_plans')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->boolean('is_included')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['referral_commission_plan_id', 'item_id'], 'ref_comm_plan_items_plan_item_unique');
        });

        Schema::create('referral_commission_plan_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referral_commission_plan_id')->constrained('referral_commission_plans')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->boolean('is_included')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['referral_commission_plan_id', 'category_id'], 'ref_comm_plan_categories_plan_category_unique');
        });

        Schema::create('referrer_commission_plan_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('referrer_id')->constrained('referrers')->cascadeOnDelete();
            $table->foreignId('referral_commission_plan_id')->constrained('referral_commission_plans')->restrictOnDelete();
            $table->string('status', 30)->default(ReferralCommissionAssignmentStatus::ACTIVE->value);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->text('assignment_reason')->nullable();
            $table->text('end_reason')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'referrer_id']);
            $table->index(['referrer_id', 'status']);
            $table->index(['referral_commission_plan_id', 'status'], 'ref_comm_plan_assignments_plan_status_idx');
            $table->index(['effective_from', 'effective_to']);
        });

        DB::statement("CREATE UNIQUE INDEX referrer_commission_plan_one_open_primary ON referrer_commission_plan_assignments (referrer_id) WHERE is_primary = true AND status = 'ACTIVE' AND effective_to IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('referrer_commission_plan_assignments');
        Schema::dropIfExists('referral_commission_plan_categories');
        Schema::dropIfExists('referral_commission_plan_items');
        Schema::dropIfExists('referral_commission_plan_tiers');
        Schema::dropIfExists('referral_commission_settings');
        Schema::dropIfExists('referral_commission_plans');
    }
};
