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
        Schema::create('item_charges', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique(); // e.g., 'FREIGHT', 'INSURANCE', 'CUSTOMS'
            $table->string('description')->nullable();
            $table->string('description_2')->nullable();
            $table->string('gen_prod_posting_group')->nullable();
            $table->string('vat_prod_posting_group')->nullable();
            $table->string('search_description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys (Optional, depending on your DB strictness)
            $table->foreign('gen_prod_posting_group')->references('code')->on('general_product_posting_groups')->nullOnDelete();
            $table->foreign('vat_prod_posting_group')->references('code')->on('vat_product_posting_groups')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_charges');
    }
};
