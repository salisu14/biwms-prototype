<?php

use App\Enums\BlockedReason;
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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_number', 20)->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Posting Groups (Critical for P&L)
            $table->foreignId('general_business_posting_group_id')
                ->constrained('general_business_posting_groups');
            $table->foreignId('customer_posting_group_id')
                ->constrained('customer_posting_groups');
            $table->string('vat_bus_posting_group', 20)->nullable();

            // Shipping
            $table->foreignId('location_id')
                ->nullable()
                ->constrained('locations');
            $table->string('shipping_agent_code', 20)->nullable();

            // Payment Terms
            $table->string('payment_terms_code', 20)->nullable();
            $table->decimal('credit_limit', 15, 2)->nullable();

            $table->boolean('blocked')->default(false);

            $table->enum('blocked_reason', array_column(BlockedReason::cases(), 'value'))
                ->default('NONE');

            $table->foreignId('pricing_group_id')
                ->nullable()
                ->after('customer_posting_group_id')
                ->constrained('pricing_groups');

            // Customer-specific price list (overrides group)
            $table->string('price_list_code', 20)->nullable()->after('pricing_group_id');

            // Pricing-specific flags
            $table->boolean('allow_discounts')->default(true);
            $table->decimal('maximum_discount_percent', 5, 2)->nullable();
            $table->boolean('price_includes_vat')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
