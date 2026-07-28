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
        if (Schema::hasTable('cost_adjustment_batches')) {
            return;
        }

        Schema::create('cost_adjustment_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_number', 80)->unique();
            $table->string('source_type', 120)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reason', 255);
            $table->boolean('dry_run')->default(true);
            $table->timestamp('run_at');
            $table->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id'], 'cost_adjustment_batches_source_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_adjustment_batches');
    }
};
