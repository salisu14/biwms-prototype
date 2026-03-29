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
        // Routings
        Schema::create('routings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description');
            $table->foreignId('item_id')->nullable()->constrained('items');
            $table->string('status')->default('CERTIFIED');
            $table->string('version')->default('1.0');
            $table->date('starting_date')->nullable();
            $table->date('ending_date')->nullable();
            $table->string('type')->default('SERIAL'); // SERIAL, PARALLEL
            $table->decimal('cost_rollup', 15, 4)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('last_modified_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routings');
    }
};
