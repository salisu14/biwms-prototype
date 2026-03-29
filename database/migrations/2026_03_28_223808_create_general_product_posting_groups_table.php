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
        Schema::create('general_product_posting_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // RETAIL, RAWMAT, SERVICE
            $table->string('description');
            $table->string('default_vat_prod_posting_group', 20)->nullable();
            $table->boolean('auto_create_vat_prod_posting_group')->default(false);
            $table->boolean('blocked')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_product_posting_groups');
    }
};
