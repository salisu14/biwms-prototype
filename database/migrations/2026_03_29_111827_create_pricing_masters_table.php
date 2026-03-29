<?php

use App\Enums\PriceListType;
use App\Enums\PriceType;
use App\Enums\PricingStatus;
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
        Schema::create('pricing_master', function (Blueprint $table) {
            $table->id();
            $table->string('price_list_code', 20)->index();
            $table->string('description')->nullable();

            // Price List Type (determines priority/fallback)
            $table->enum('price_list_type', array_column(PriceListType::cases(), 'value'))
                ->default('ALL_CUSTOMERS');

            // HIERARCHY: Who does this price apply to?
            // Level 1: Customer or Group
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->comment('NULL = all customers or use pricing_group_id');

            $table->foreignId('pricing_group_id')
                ->nullable()
                ->constrained('pricing_groups')
                ->comment('NULL = customer-specific or general');

            // Level 2: Item specifics
            $table->foreignId('item_id')
                ->nullable()
                ->constrained('items')
                ->comment('NULL = all items (rare, for group discounts)');

            $table->string('variant_code', 20)->nullable();
            $table->string('unit_of_measure_code', 20)->nullable();

            // Level 3: Context
            $table->foreignId('location_id')
                ->nullable()
                ->constrained('locations')
                ->comment('Location-specific pricing');

            $table->string('currency_code', 3)->default('USD');

            // THE PRICE
            $table->enum('price_type', array_column(PriceType::cases(), 'value'))
                ->default('UNIT_PRICE');

            $table->decimal('unit_price', 15, 4)->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('discount_amount', 15, 4)->nullable();
            $table->decimal('cost_plus_percent', 5, 2)->nullable();
            $table->decimal('minimum_quantity', 15, 4)->default(0);
            $table->decimal('maximum_quantity', 15, 4)->nullable();

            // Quantity Break Pricing (Tiered)
            $table->boolean('allow_quantity_breaks')->default(false);
            // Breaks stored in pricing_master_quantity_breaks table

            // Date effectiveness
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->time('start_time')->nullable(); // For intraday promotions
            $table->time('end_time')->nullable();

            // Day of week restrictions (e.g., weekend pricing)
            $table->json('applicable_days')->nullable(); // ['mon', 'tue', 'wed']

            // Minimum order requirements
            $table->decimal('minimum_order_amount', 15, 2)->nullable();
            $table->decimal('minimum_order_quantity', 15, 4)->nullable();

            // Lead time requirements (price valid only if ordered X days in advance)
            $table->integer('minimum_lead_time_days')->nullable();

            // Status and approval
            $table->enum('status', array_column(PricingStatus::cases(), 'value'))
                ->default('DRAFT');

            $table->foreignId('approved_by')->references('users');
            $table->timestamp('approved_at')->nullable();

            // Audit
            $table->string('created_by');
            $table->string('modified_by')->nullable();
            $table->text('modification_reason')->nullable();

            // Priority (higher = checked first)
            $table->integer('priority')->default(0);

            // Versioning
            $table->boolean('is_current_version')->default(true);
            $table->unsignedBigInteger('replaces_id')->nullable();
            $table->unsignedBigInteger('replaced_by_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Critical indexes for price lookup performance
            $table->index(['price_list_type', 'customer_id', 'pricing_group_id', 'item_id', 'status'], 'idx_price_lookup');
            $table->index(['item_id', 'variant_code', 'unit_of_measure_code', 'currency_code', 'status'], 'idx_item_price');
            $table->index(['start_date', 'end_date', 'status'], 'idx_date_effective');
            $table->index(['price_list_code', 'is_current_version'], 'idx_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_masters');
    }
};
