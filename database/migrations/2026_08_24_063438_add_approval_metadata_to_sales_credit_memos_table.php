<?php

declare(strict_types=1);

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
        if (! Schema::hasColumn('sales_credit_memos', 'approver_id')) {
            Schema::table('sales_credit_memos', function (Blueprint $table): void {
                $table->foreignId('approver_id')
                    ->nullable()
                    ->after('posted_by')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('sales_credit_memos', 'approved_at')) {
            Schema::table('sales_credit_memos', function (Blueprint $table): void {
                $table->timestamp('approved_at')
                    ->nullable()
                    ->after('approver_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('sales_credit_memos', 'approved_at')) {
            Schema::table('sales_credit_memos', function (Blueprint $table): void {
                $table->dropColumn('approved_at');
            });
        }

        if (Schema::hasColumn('sales_credit_memos', 'approver_id')) {
            Schema::table('sales_credit_memos', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('approver_id');
            });
        }
    }
};
