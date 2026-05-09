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
        Schema::create('inventory_adjustment_journals', function (Blueprint $table) {
            $table->id();
            $table->string('journal_batch_name')->unique();
            $table->string('description')->nullable();
            $table->date('posting_date');
            $table->date('document_date')->nullable();
            $table->enum('status', ['Open', 'Released', 'Posted'])->default('Open');
            $table->string('reason_code')->nullable();
            $table->string('location_code')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users');
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_journals');
    }
};
