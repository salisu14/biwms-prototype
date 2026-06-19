<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->foreignId('posted_by')->nullable()->change();
            $table->timestamp('posted_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->foreignId('posted_by')->nullable(false)->change();
            $table->timestamp('posted_at')->nullable(false)->change();
        });
    }
};
