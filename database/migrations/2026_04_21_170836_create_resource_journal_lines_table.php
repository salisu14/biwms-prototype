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
        Schema::create('resource_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_line_id')->constrained('journal_lines')->onDelete('cascade');
            // Resource Journal specific fields
            $table->enum('entry_type', ['Usage', 'Sale', 'Purchase', 'Charge']);
            $table->unsignedBigInteger('resource_id')->nullable(); // Resource table may not exist; constraint deferred
            $table->decimal('quantity', 15, 4); // Hours or units
            $table->string('unit_of_measure_code', 20)->default('HOUR');
            $table->decimal('direct_unit_cost', 15, 4)->nullable();
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->decimal('total_price', 15, 4)->default(0);
            $table->foreignId('job_id')->nullable()->constrained('jobs');
            $table->string('job_task_no', 50)->nullable();
            $table->foreignId('work_type_code')->nullable();
            $table->string('chargeable', 10)->default('Billable'); // Billable, Non-Billable, Both
            $table->foreignId('service_order_id')->nullable();
            $table->string('service_item_line_no', 50)->nullable();
            $table->foreignId('allocation_id')->nullable(); // For allocation journals
            $table->text('time_sheet_description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_journal_lines');
    }
};
