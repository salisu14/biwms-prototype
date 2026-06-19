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
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 50); // e.g., 'employees', 'items'
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('total_records')->default(0);
            $table->integer('synced_records')->default(0);
            $table->text('errors')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
