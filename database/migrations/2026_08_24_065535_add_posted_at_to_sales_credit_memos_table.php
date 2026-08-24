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
        if (! Schema::hasColumn('sales_credit_memos', 'posted_at')) {
            Schema::table('sales_credit_memos', function (Blueprint $table): void {
                $table->timestamp('posted_at')
                    ->nullable()
                    ->after('posted_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('sales_credit_memos', 'posted_at')) {
            Schema::table('sales_credit_memos', function (Blueprint $table): void {
                $table->dropColumn('posted_at');
            });
        }
    }
};
