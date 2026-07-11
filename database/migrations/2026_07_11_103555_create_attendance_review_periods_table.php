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
        Schema::create('attendance_review_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->date('date_from')->index();
            $table->date('date_to')->index();
            $table->string('status')->default('draft')->index();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'date_from', 'date_to'], 'attendance_review_period_range_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_review_periods');
    }
};
