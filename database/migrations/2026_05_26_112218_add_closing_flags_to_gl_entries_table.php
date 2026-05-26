<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_entries', function (Blueprint $table) {
            $table->boolean('is_closing_entry')->default(false)->after('reconciled');
            $table->integer('closing_fiscal_year')->nullable()->after('is_closing_entry');
            $table->index(['is_closing_entry', 'closing_fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::table('gl_entries', function (Blueprint $table) {
            $table->dropIndex(['is_closing_entry', 'closing_fiscal_year']);
            $table->dropColumn(['is_closing_entry', 'closing_fiscal_year']);
        });
    }
};
