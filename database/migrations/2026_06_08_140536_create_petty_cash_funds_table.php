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
        Schema::create('petty_cash_funds', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('location')->nullable();
            $table->foreignId('custodian_id')->nullable()->constrained('users');
            $table->decimal('imprest_amount', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->foreignId('chart_of_account_id')
                ->nullable()
                ->after('id') // Place it wherever makes sense for your schema
                ->constrained('chart_of_accounts')
                ->nullOnDelete(); // If the account is deleted, set this to null

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_funds');
    }
};
