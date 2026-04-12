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
        Schema::create('fixed_asset_journal_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->foreignId('number_series_id')->nullable()->constrained('number_series');
            $table->string('source_code')->nullable();
            $table->timestamps();
        });

        Schema::create('fixed_asset_journal_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('fixed_asset_journal_templates')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();

            $table->unique(['template_id', 'name']);
        });

        Schema::create('fixed_asset_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('fixed_asset_journal_batches')->cascadeOnDelete();
            $table->integer('line_no')->default(10000);
            $table->date('posting_date');
            $table->string('document_no');
            $table->foreignId('asset_id')->constrained('assets');
            $table->string('fa_posting_type'); // Acquisition, Depreciation, Disposal, etc.
            $table->decimal('amount', 20, 4);
            $table->string('description')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['batch_id', 'line_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_journal_lines');
        Schema::dropIfExists('fixed_asset_journal_batches');
        Schema::dropIfExists('fixed_asset_journal_templates');
    }
};
