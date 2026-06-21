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
        // Fixed Asset Depreciation Ledger
        Schema::create('fixed_asset_depreciation_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets');
            $table->date('depreciation_date');
            $table->string('depreciation_period'); // YYYY-MM
            $table->decimal('depreciation_amount', 15, 2);
            $table->decimal('accumulated_depreciation', 15, 2);
            $table->decimal('net_book_value', 15, 2);
            $table->string('posted_document_no')->nullable();
            $table->boolean('posted')->default(false);
            $table->timestamps();

            $table->index(['fixed_asset_id', 'depreciation_period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_depreciation_ledger');
    }
};
