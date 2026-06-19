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
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('production_bom_id')
                ->nullable()
                ->after('item_category_id')
                ->constrained('production_boms')
                ->nullOnDelete();

            $table->foreignId('routing_id')
                ->nullable()
                ->after('production_bom_id')
                ->constrained('routings')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['production_bom_id']);
            $table->dropForeign(['routing_id']);
            $table->dropColumn(['production_bom_id', 'routing_id']);
        });
    }
};
