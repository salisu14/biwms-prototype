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
        Schema::table('social_security_tiers', function (Blueprint $table) {
            $table->decimal('to_salary', 12, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_security_tiers', function (Blueprint $table) {
            $table->decimal('to_salary', 12, 2)->nullable(false)->change();
        });
    }
};
