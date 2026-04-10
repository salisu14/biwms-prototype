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
        Schema::create('payroll_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 20)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', array_column(\App\Enums\PayrollStatus::cases(), 'value'))->default(\App\Enums\PayrollStatus::DRAFT->value);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_documents');
    }
};
