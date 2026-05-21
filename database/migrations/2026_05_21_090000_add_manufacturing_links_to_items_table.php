<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('production_bom_id')
                ->nullable()
                ->constrained('production_boms')
                ->nullOnDelete()
                ->after('item_type');

            $table->foreignId('routing_id')
                ->nullable()
                ->constrained('routings')
                ->nullOnDelete()
                ->after('production_bom_id');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['production_bom_id']);
            $table->dropForeign(['routing_id']);
            $table->dropColumn(['production_bom_id', 'routing_id']);
        });
    }
};
