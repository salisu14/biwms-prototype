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
        Schema::create('currency_buffers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('currency_id')->constrained('currencies');
            $table->string('buffer_type', 30); // 'receivable', 'payable', 'bank'
            $table->foreignId('entity_id'); // Polymorphic: customer, vendor, bank_account

            $table->decimal('amount_lcy', 18, 4); // Amount in Local Currency
            $table->decimal('amount_fcy', 18, 4); // Amount in Foreign Currency
            $table->decimal('remaining_amount_lcy', 18, 4);
            $table->decimal('remaining_amount_fcy', 18, 4);

            $table->decimal('original_exch_rate', 18, 6);
            $table->decimal('current_exch_rate', 18, 6);

            $table->decimal('unrealized_gain_loss', 18, 4)->default(0);
            $table->boolean('adjusted')->default(false);

            $table->date('posting_date');
            $table->date('due_date')->nullable();

            $table->timestamps();

            $table->index(['currency_id', 'buffer_type', 'adjusted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_buffers');
    }
};
