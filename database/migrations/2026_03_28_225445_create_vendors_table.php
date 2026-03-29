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
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('vendor_number', 20)->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Address
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->nullable();

            // Business information
            $table->string('tax_id', 50)->nullable();
            $table->string('payment_terms', 100)->nullable();
            $table->char('currency', 3)->default('NGN');

            // Operational
            $table->integer('lead_time_days')->nullable();
            $table->decimal('minimum_order_amount', 15, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            // Posting Groups
            $table->foreignId('general_business_posting_group_id')
                ->constrained('general_business_posting_groups');
            $table->foreignId('vendor_posting_group_id')
                ->constrained('vendor_posting_groups');
            $table->string('vat_bus_posting_group', 20)->nullable();

            // Payment Terms
            $table->string('payment_terms_code', 20)->nullable();

            $table->boolean('blocked')->default(false);

            $table->enum('blocked_reason', array_column(BlockedReason::cases(), 'value'))
                ->default('NONE');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
