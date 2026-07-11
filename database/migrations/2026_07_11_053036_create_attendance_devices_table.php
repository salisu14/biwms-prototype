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
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_location_id')->nullable()->constrained('attendance_locations')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('device_type')->default('web');
            $table->string('serial_number')->nullable()->unique();
            $table->string('api_key_hash')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_devices');
    }
};
