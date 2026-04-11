<?php

use App\Enums\CalculationMethod;
use App\Enums\PayCodeType;
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
        Schema::create('pay_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->enum('type', array_column(PayCodeType::cases(), 'value'));
            $table->enum('calculation_method', array_column(CalculationMethod::cases(), 'value'))->default(CalculationMethod::FIXED_AMOUNT->value);
            $table->decimal('default_amount', 15, 4)->nullable(); // Used as amount or percentage
            $table->foreignId('gl_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pay_codes');
    }
};
