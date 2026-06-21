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
        Schema::create('reason_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description');
            $table->string('default_location_code')->nullable();
            $table->string('default_bin_code')->nullable();
            $table->string('inventory_adjustment_account')->nullable()->comment('G/L Account for adjustment posting');
            $table->string('inventory_account')->nullable()->comment('G/L Account for inventory');
            $table->boolean('blocked')->default(false);
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reason_codes');
    }
};
