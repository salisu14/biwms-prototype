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
        Schema::create('item_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')
                ->constrained('item_masters', 'id')
                ->onDelete('cascade');
            $table->string('lot_number', 50);
            $table->string('supplier_lot', 50)->nullable();
            $table->date('receipt_date');
            $table->date('expiry_date');
            $table->date('retest_date')->nullable();
            $table->decimal('quantity_received', 18, 4);
            $table->decimal('quantity_remaining', 18, 4)->default(0);
            $table->enum('status', ['QUARANTINE', 'APPROVED', 'REJECTED', 'EXPIRED', 'RECALLED'])
                ->default('QUARANTINE');
            $table->string('coa_reference', 100)->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'lot_number']);
            $table->index(['expiry_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_lots');
    }
};
