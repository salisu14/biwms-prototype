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
        Schema::create('vat_masters', function (Blueprint $table) {
            $table->id();

            $table->string('code', 20)->unique();
            $table->string('description', 100);
            $table->string('purchase_account_number', 50);
            $table->string('sales_account_number', 50);
            $table->decimal('percentage', 5, 2)->default(0);

            $table->timestamps();

            // Indexes
            $table->index('code');
            $table->index('percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vat_masters');
    }
};
