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
        // Work Center Calendar
        Schema::create('work_center_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_center_id')->constrained('work_centers');
            $table->date('date');
            $table->boolean('is_working_day')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('capacity', 15, 4)->default(0);
            $table->decimal('efficiency', 5, 2)->default(100);
            $table->string('absence_code')->nullable(); // Holiday, etc.
            $table->timestamps();

            $table->unique(['work_center_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_center_calendars');
    }
};
