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
        Schema::create('general_business_posting_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // DOMESTIC, EXPORT, EU
            $table->string('description');

            // IMPROVEMENT: Aligned naming with the "Default" logic for better clarity
            $table->foreignId('default_vat_business_posting_group_id')
                ->nullable()
                ->constrained('vat_business_posting_groups')
                ->nullOnDelete();

            $table->boolean('auto_create_vat_bus_posting_group')
                ->default(false)
                ->comment('Automatically assign default VAT business group');

            $table->boolean('blocked')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_business_posting_groups');
    }
};
