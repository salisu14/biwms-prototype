<?php

use App\Enums\CustomerReferralStatus;
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
        Schema::create('customer_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('referrer_id')->constrained('referrers')->restrictOnDelete();
            $table->string('status', 30)->default(CustomerReferralStatus::ACTIVE->value);
            $table->boolean('is_primary')->default(true);
            $table->date('referred_at')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('referral_source')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ended_at')->nullable();
            $table->text('end_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'customer_id']);
            $table->index(['business_id', 'referrer_id']);
            $table->index(['customer_id', 'status']);
            $table->index(['referrer_id', 'status']);
            $table->index('effective_from');
            $table->index('effective_to');
        });

        DB::statement("CREATE UNIQUE INDEX customer_referrals_one_open_active_primary ON customer_referrals (customer_id) WHERE is_primary = true AND status = 'ACTIVE' AND effective_to IS NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_referrals');
    }
};
