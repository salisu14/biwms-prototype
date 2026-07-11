<?php

declare(strict_types=1);

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
        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('crosses_midnight')->default(false);
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('grace_minutes')->default(0);
            $table->unsignedInteger('early_departure_grace_minutes')->default(0);
            $table->unsignedInteger('overtime_threshold_minutes')->default(0);
            $table->boolean('is_weekend')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_shifts');
    }
};
