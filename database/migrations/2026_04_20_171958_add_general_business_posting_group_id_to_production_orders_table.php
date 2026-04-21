<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('production_orders', 'general_business_posting_group_id')) {
                $table->foreignId('general_business_posting_group_id')
                    ->after('inventory_posting_group_id')
                    ->nullable()
                    ->constrained('general_business_posting_groups');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('general_business_posting_group_id');
        });
    }
};
