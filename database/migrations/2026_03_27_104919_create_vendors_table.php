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
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();

            // Primary identifiers
            $table->string('vendor_code', 50)->unique();
            $table->string('vendor_name', 255);

            // Contact information
            $table->string('contact_person', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();

            // Address
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->nullable();

            // Business information
            $table->string('tax_id', 50)->nullable();
            $table->string('payment_terms', 100)->nullable();
            $table->char('currency', 3)->default('USD');

            // Operational
            $table->integer('lead_time_days')->nullable();
            $table->decimal('minimum_order_amount', 15, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('vendor_code');
            $table->index('vendor_name');
            $table->index('is_active');
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
