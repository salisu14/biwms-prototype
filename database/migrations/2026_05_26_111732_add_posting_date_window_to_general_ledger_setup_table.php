<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_ledger_setup', function (Blueprint $table) {
            $table->date('allow_posting_from')->nullable()->after('company_name');
            $table->date('allow_posting_to')->nullable()->after('allow_posting_from');
        });
    }

    public function down(): void
    {
        Schema::table('general_ledger_setup', function (Blueprint $table) {
            $table->dropColumn(['allow_posting_from', 'allow_posting_to']);
        });
    }
};
