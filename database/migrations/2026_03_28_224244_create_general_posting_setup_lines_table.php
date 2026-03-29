<?php

use App\Enums\LineType;
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
        Schema::create('general_posting_setup_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('general_posting_setup_id')
                ->constrained('general_posting_setups')
                ->onDelete('cascade');

            $table->enum('line_type', array_column(LineType::cases(), 'value'))
                ->default('SALES');

            $table->foreignId('chart_of_account_id')
                ->constrained('chart_of_accounts');

            $table->timestamps();

            // One account per type per setup
            $table->unique([
                'general_posting_setup_id',
                'line_type'
            ], 'unique_setup_line_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_posting_setup_lines');
    }
};
