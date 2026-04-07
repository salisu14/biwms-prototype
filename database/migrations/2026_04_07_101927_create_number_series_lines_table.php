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
        // Number Series Line (BC Table 308)
        Schema::create('number_series_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('number_series_id')->constrained('number_series')->onDelete('cascade');

            $table->date('starting_date');
            $table->date('ending_date')->nullable();

            $table->string('prefix', 20)->nullable();
            $table->string('suffix', 20)->nullable();
            $table->integer('no_of_digits')->default(5);

            $table->bigInteger('starting_no')->nullable();
            $table->bigInteger('ending_no')->nullable();
            $table->bigInteger('last_no_used')->nullable();

            $table->integer('increment_by')->default(1);
            $table->boolean('blocked')->default(false);

            $table->timestamps();

            // Index for performance when looking up valid series for a specific document date
            $table->index(['number_series_id', 'starting_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('number_series_lines');
    }
};
