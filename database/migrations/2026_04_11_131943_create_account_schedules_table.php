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
        // Migration
        Schema::create('account_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('default_column_layout')->nullable();
            $table->boolean('show_amounts_in_lcy')->default(true);
            $table->timestamps();
        });

        Schema::create('account_schedule_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_schedule_id')->constrained();
            $table->integer('line_no');
            $table->string('description');
            $table->enum('totaling_type', ['posting_accounts', 'accounts', 'formula', 'set_mob']);
            $table->string('totaling')->nullable(); // Account codes or formula
            $table->integer('indentation')->default(0);
            $table->boolean('bold')->default(false);
            $table->boolean('italic')->default(false);
            $table->boolean('underline')->default(false);
            $table->boolean('new_page')->default(false);
            $table->boolean('show_opposite_sign')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_schedules');
    }
};
