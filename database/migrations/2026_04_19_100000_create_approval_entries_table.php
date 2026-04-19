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
        Schema::create('approval_entries', function (Blueprint $table) {
            $table->id();

            // Polymorphic Relationship
            $table->morphs('approvable');

            $table->unsignedInteger('sequence_no'); // approval order

            $table->foreignId('approver_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('status', 20)
                ->default('created'); // created, approved, rejected, delegated

            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('delegated_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('delegated_at')->nullable();

            $table->text('comment')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['approvable_type', 'approvable_id', 'sequence_no'], 'approvable_sequence_index');
            $table->index(['approver_id']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_entries');
    }
};
