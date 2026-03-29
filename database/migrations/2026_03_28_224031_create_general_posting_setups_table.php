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
        Schema::create('general_posting_setups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('general_business_posting_group_id')
                ->constrained('general_business_posting_groups');
            $table->foreignId('general_product_posting_group_id')
                ->constrained('general_product_posting_groups');
            $table->boolean('blocked')->default(false);
            $table->timestamps();

            // Unique combination
            $table->unique([
                'general_business_posting_group_id',
                'general_product_posting_group_id'
            ], 'unique_posting_setup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_posting_setups');
    }
};
