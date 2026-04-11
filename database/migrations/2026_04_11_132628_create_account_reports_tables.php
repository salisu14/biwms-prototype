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
        Schema::create('account_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('account_schedule_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('account_schedules')->onDelete('cascade');
            $table->integer('line_no')->default(0);
            $table->string('row_no', 20)->nullable(); // e.g., '10', '20'
            $table->string('description');
            
            // Configuration
            $table->string('totaling_type')->default('Posting Accounts'); // Enum: Posting Accounts, Total Accounts, Formula, Underline
            $table->string('totaling')->nullable(); // Account range or formula
            $table->string('row_type')->default('Net Change'); // Enum: Net Change, Balance at Date, Beginning Balance
            $table->string('amount_type')->default('Net Amount'); // Enum: Net Amount, Debit Amount, Credit Amount
            
            // Formatting
            $table->boolean('show_opposite_sign')->default(false);
            $table->boolean('bold')->default(false);
            $table->boolean('italic')->default(false);
            $table->boolean('underline')->default(false);
            $table->integer('indentation')->default(0);
            $table->boolean('new_page')->default(false);
            
            $table->timestamps();
            
            $table->index(['schedule_id', 'line_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_schedule_lines');
        Schema::dropIfExists('account_schedules');
    }
};
