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
        Schema::create('shipment_methods', function (Blueprint $table) {
            $table->id();

            // BC-standard fields
            $table->string('code', 20)->unique(); // FOB, CIF, DDP, EXW, etc.
            $table->string('description', 100);
            $table->string('search_description', 100)->nullable();

            // Extended fields (beyond basic BC)
            $table->string('incoterm_code', 10)->nullable(); // Incoterms 2020: EXW, FCA, CPT, etc.
            $table->boolean('is_incoterm')->default(false);

            // Transport mode
            $table->string('transport_mode', 20)->nullable(); // air, sea, road, rail, multimodal

            // Insurance & liability
            $table->boolean('seller_pays_insurance')->default(false);
            $table->boolean('seller_pays_freight')->default(false);
            $table->boolean('seller_pays_duty')->default(false);

            // Default shipping agent
            $table->foreignId('default_shipping_agent_id')->nullable()->constrained('shipping_agents');

            // Service level defaults
            $table->string('default_service_code', 20)->nullable(); // standard, express, overnight

            // Dimensions for reporting (optional extension)
            $table->string('shortcut_dimension_1_code', 20)->nullable();
            $table->string('shortcut_dimension_2_code', 20)->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('blocked')->default(false);

            // Metadata
            $table->text('notes')->nullable();
            $table->json('extended_fields')->nullable(); // For Master Data Information pattern

            $table->timestamps();
            $table->softDeletes();

            $table->index('incoterm_code');
            $table->index('is_active');
            $table->index('blocked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_methods');
    }
};
