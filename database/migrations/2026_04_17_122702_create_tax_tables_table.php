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
        Schema::create('tax_tables', function (Blueprint $table) {
            $table->id();
            $table->string('jurisdiction', 50);
            $table->date('effective_date');
            $table->string('name')->nullable()->after('id');
            $table->string('country_code', 10)->nullable()->after('name');
            $table->string('state_code', 10)->nullable()->after('country_code');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_tables');
    }
};
