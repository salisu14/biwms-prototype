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
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();

            $table->foreignId('item_id')->constrained()->cascadeOnDelete();

            // Pricing scope
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();

//            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();

            // Price
            $table->decimal('price', 18, 2);
            $table->string('currency')->default('NGN');

            // Validity
            $table->date('starting_date');
            $table->date('ending_date')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['item_id', 'customer_id']);
            $table->index(['item_id', 'customer_group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_lists');
    }
};
