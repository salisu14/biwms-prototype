<?php

use App\Enums\DocumentStatus;
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
        Schema::create('document_headers', function (Blueprint $table) {
            $table->id();
            $table->enum('doc_type', [
                'PURCHASE_ORDER',
                'PRODUCTION_ORDER',
                'SALES_ORDER',
                'TRANSFER_ORDER',
                'ADJUSTMENT',
                'RETURN',
                'SCRAP'
            ]);
            $table->string('doc_no', 50)->unique();
            $table->date('doc_date');
            $table->date('posting_date');
            $table->string('status', 20)
                ->default(DocumentStatus::OPEN->value); // DocumentStatus enum
            $table->foreignId('created_by')
                ->constrained('users', 'id')
                ->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['doc_type', 'status']);
            $table->index(['doc_no', 'posting_date']);
            $table->index('doc_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_headers');
    }
};
