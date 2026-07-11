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
        Schema::create('employee_attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id_card_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_location_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('correction_request_id')->nullable()->index();
            $table->string('event_type');
            $table->timestamp('occurred_at')->index();
            $table->date('attendance_date')->index();
            $table->string('source')->default('web');
            $table->string('card_token_hash')->nullable()->index();
            $table->string('verification_result')->nullable()->index();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'attendance_date', 'event_type'], 'employee_attendance_events_daily_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_events');
    }
};
