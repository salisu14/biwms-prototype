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
        Schema::table('price_change_template_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('price_change_template_lines', 'business_id')) {
                $table->foreignId('business_id')
                    ->nullable()
                    ->after('category_id')
                    ->constrained('businesses')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('price_change_template_lines', 'customer_group_id')) {
                $table->foreignId('customer_group_id')
                    ->nullable()
                    ->after('business_id')
                    ->constrained('customer_groups')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_change_template_lines', function (Blueprint $table) {
            if (Schema::hasColumn('price_change_template_lines', 'customer_group_id')) {
                $table->dropConstrainedForeignId('customer_group_id');
            }

            if (Schema::hasColumn('price_change_template_lines', 'business_id')) {
                $table->dropConstrainedForeignId('business_id');
            }
        });
    }
};
