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
        if (Schema::hasTable('costing_periods')) {
            return;
        }

        Schema::create('costing_periods', function (Blueprint $table): void {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('name', 80)->nullable();
            $table->boolean('is_closed')->default(false);
            $table->date('adjustment_allowed_through')->nullable();
            $table->date('cost_adjustment_posting_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['start_date', 'end_date'], 'costing_periods_date_range_unique');
            $table->index(['is_closed', 'start_date', 'end_date'], 'costing_periods_status_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costing_periods');
    }
};
