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
        Schema::table('item_skus', function (Blueprint $table) {
            // Fix item_id FK
            try {
                $table->dropForeign('item_skus_item_id_foreign');
            } catch (\Exception $e) {}
            
            $table->foreign('item_id')
                ->references('id')
                ->on('items')
                ->onDelete('cascade');

            // Fix location_id FK
            try {
                $table->dropForeign('item_skus_location_id_foreign');
            } catch (\Exception $e) {}

            $table->foreign('location_id')
                ->references('id')
                ->on('locations')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_skus', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->foreign('item_id')
                ->references('id')
                ->on('item_masters')
                ->onDelete('cascade');

            $table->dropForeign(['location_id']);
            $table->foreign('location_id')
                ->references('id')
                ->on('location_masters')
                ->onDelete('cascade');
        });
    }
};
