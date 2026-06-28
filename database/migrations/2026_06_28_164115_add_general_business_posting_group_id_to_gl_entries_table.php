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
        Schema::table('gl_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('gl_entries', 'general_business_posting_group_id')) {
                $table->foreignId('general_business_posting_group_id')
                    ->nullable()
                    ->after('chart_of_account_id')
                    ->constrained('general_business_posting_groups')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gl_entries', function (Blueprint $table) {
            if (Schema::hasColumn('gl_entries', 'general_business_posting_group_id')) {
                $table->dropConstrainedForeignId('general_business_posting_group_id');
            }
        });
    }
};
