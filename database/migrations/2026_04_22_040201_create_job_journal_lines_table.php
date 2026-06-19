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
        Schema::create('job_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_line_id')->constrained('journal_lines')->onDelete('cascade');
            $table->enum('entry_type', ['Resource', 'Item', 'G/L Account']);
            $table->foreignId('job_id')->constrained('jobs');
            $table->string('job_task_no', 50);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->foreignId('item_id')->nullable()->constrained('items');
            $table->string('gl_account_no', 50)->nullable();
            $table->decimal('quantity', 15, 4);
            $table->string('unit_of_measure_code', 20);
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->decimal('total_price', 15, 4)->default(0);
            $table->decimal('line_discount_percent', 5, 2)->default(0);
            $table->decimal('line_discount_amount', 15, 4)->default(0);
            $table->enum('chargeable', ['Billable', 'Non-Billable', 'Both'])->default('Billable');
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->string('bin_code', 20)->nullable();
            $table->foreignId('work_type_code')->nullable();
            $table->foreignId('service_order_id')->nullable();
            $table->text('description_2')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_journal_lines');
    }
};
