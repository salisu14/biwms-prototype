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
        Schema::create('shipping_agents', function (Blueprint $table) {
            $table->id();

            // BC-standard fields
            $table->string('code', 20)->unique(); // FEDEX, UPS, DHL, etc.
            $table->string('name', 100);
            $table->string('search_name', 100)->nullable();

            // Contact information
            $table->string('address', 100)->nullable();
            $table->string('address_2', 50)->nullable();
            $table->string('city', 30)->nullable();
            $table->string('post_code', 20)->nullable();
            $table->string('country_code', 10)->nullable();
            $table->string('phone_no', 30)->nullable();
            $table->string('email', 80)->nullable();
            $table->string('website', 100)->nullable();

            // Account information
            $table->string('account_no', 50)->nullable(); // Your account number with carrier
            $table->string('api_key', 255)->nullable(); // For tracking integration
            $table->string('api_endpoint', 255)->nullable();

            // Service defaults
            $table->string('default_service_type', 30)->default('ground'); // ShippingAgentServiceType
            $table->decimal('default_insurance_amount', 18, 4)->nullable();
            $table->boolean('requires_insurance')->default(false);

            // Charges
            $table->decimal('base_charge', 18, 4)->default(0);
            $table->decimal('fuel_surcharge_percent', 5, 2)->default(0);
            $table->decimal('handling_charge', 18, 4)->default(0);

            // Dimensions for reporting
            $table->string('shortcut_dimension_1_code', 20)->nullable();
            $table->string('shortcut_dimension_2_code', 20)->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('blocked')->default(false);

            // Metadata
            $table->text('notes')->nullable();
            $table->json('extended_fields')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('blocked');
            $table->index('country_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_agents');
    }
};
