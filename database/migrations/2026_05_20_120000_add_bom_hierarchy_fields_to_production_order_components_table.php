<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_order_components', function (Blueprint $table) {
            $table->unsignedSmallInteger('bom_level')->default(1)->after('line_number');
            $table->string('bom_path')->nullable()->after('bom_level');
            $table->string('source_bom_code')->nullable()->after('bom_path');

            $table->index(['production_order_id', 'bom_level'], 'poc_order_bom_level_idx');
            $table->index('source_bom_code', 'poc_source_bom_code_idx');
        });
    }

    public function down(): void
    {
        Schema::table('production_order_components', function (Blueprint $table) {
            $table->dropIndex('poc_order_bom_level_idx');
            $table->dropIndex('poc_source_bom_code_idx');
            $table->dropColumn(['bom_level', 'bom_path', 'source_bom_code']);
        });
    }
};
