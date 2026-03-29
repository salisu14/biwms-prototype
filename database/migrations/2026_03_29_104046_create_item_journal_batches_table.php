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
        Schema::create('item_journal_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_journal_template_id')
                ->constrained('item_journal_templates');
            $table->string('name', 20);
            $table->string('description')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->boolean('blocked')->default(false);
            $table->timestamps();

            $table->unique(['item_journal_template_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_journal_batches');
    }
};
