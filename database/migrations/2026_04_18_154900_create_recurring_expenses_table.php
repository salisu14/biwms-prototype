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
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('description')->nullable();

            // Core Transaction Template
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');
            $table->foreignId('category_id')->nullable()->constrained('expense_categories');
            $table->string('category_code')->nullable();
            $table->decimal('amount', 15, 4);
            $table->foreignId('currency_id')->nullable()->constrained('currencies');

            // Scheduling
            $table->string('frequency')->comment('daily, weekly, monthly, quarterly, yearly');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('last_occurrence_at')->nullable();
            $table->date('next_occurrence_at');
            $table->integer('interval')->default(1);

            // Control
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_post')->default(false);

            // Financials
            $table->foreignId('dimension_set_id')->nullable()->constrained('dimension_sets');
            $table->string('shortcut_dimension_1_code')->nullable();
            $table->string('shortcut_dimension_2_code')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_expenses');
    }
};
