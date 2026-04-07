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
        Schema::create('default_dimensions', function (Blueprint $table) {
            $table->id();
            $table->string('table_id', 50); // Entity type: customer, item, vendor, etc.
            $table->string('no', 50); // Entity number (customer_no, item_no, etc.)
            $table->string('dimension_code', 20);
            $table->string('dimension_value_code', 20)->nullable();
            $table->enum('value_posting', ['same_code', 'no_code', 'code_mandatory', 'same_code_mandatory'])->default('same_code');
            $table->boolean('blocked')->default(false);
            $table->timestamps();

            $table->unique(['table_id', 'no', 'dimension_code']);
            $table->index(['table_id', 'no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('default_dimensions');
    }
};
