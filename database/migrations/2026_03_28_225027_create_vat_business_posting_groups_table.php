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
        Schema::create('vat_business_posting_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // e.g., 'DOMESTIC', 'EU', 'EXPORT'
            $table->string('description');
            $table->boolean('blocked')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vat_business_posting_groups');
    }
};
