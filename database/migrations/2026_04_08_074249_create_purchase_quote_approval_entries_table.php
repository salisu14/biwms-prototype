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
        Schema::create('purchase_quote_approval_entries', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Core Relationships
            |--------------------------------------------------------------------------
            */
            $table->foreignId('purchase_quote_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('sequence_no'); // approval order

            $table->foreignId('approver_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->string('status', 20)
                ->default('created'); // created, approved, rejected, delegated

            /*
            |--------------------------------------------------------------------------
            | Approval Tracking
            |--------------------------------------------------------------------------
            */
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

            /*
            |--------------------------------------------------------------------------
            | Delegation
            |--------------------------------------------------------------------------
            */
            $table->foreignId('delegated_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('delegated_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */
            $table->text('comment')->nullable();

            /*
            |--------------------------------------------------------------------------
            | System
            |--------------------------------------------------------------------------
            */
            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes (VERY IMPORTANT)
            |--------------------------------------------------------------------------
            */
            $table->index(['purchase_quote_id', 'sequence_no']);
            $table->index(['approver_id']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_quote_approval_entries');
    }
};
