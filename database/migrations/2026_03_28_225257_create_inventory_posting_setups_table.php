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
        Schema::create('inventory_posting_setups', function (Blueprint $table) {
            $table->id();

            // Matrix dimensions
            $table->foreignId('location_id')
                ->nullable() // NULL = default for all locations
                ->constrained('locations');
            $table->foreignId('inventory_posting_group_id')
                ->constrained('inventory_posting_groups');

            // G/L Accounts
            $table->foreignId('inventory_account_id')
                ->constrained('chart_of_accounts'); // Balance sheet asset
            $table->foreignId('inventory_account_interim_id')
                ->nullable()
                ->constrained('chart_of_accounts'); // Received not invoiced
            $table->foreignId('wip_account_id')
                ->nullable()
                ->constrained('chart_of_accounts'); // Work in process

            $table->timestamps();

            // Unique combination
            $table->unique([
                'location_id',
                'inventory_posting_group_id'
            ], 'unique_inventory_setup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_posting_setups');
    }
};
