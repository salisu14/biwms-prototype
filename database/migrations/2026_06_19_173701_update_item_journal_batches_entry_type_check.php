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
        // Drop the old restrictive constraint
        DB::statement('ALTER TABLE item_journal_batches DROP CONSTRAINT IF EXISTS item_journal_batches_default_entry_type_check;');

        // Add the new constraint including all your enum values
        DB::statement("
            ALTER TABLE item_journal_batches
            ADD CONSTRAINT item_journal_batches_default_entry_type_check
            CHECK (default_entry_type IN (
                'purchase',
                'sale',
                'positive_adjustment',
                'negative_adjustment',
                'transfer',
                'consumption',
                'output',
                'prod_consumption',
                'assembly_output',
                'assembly_consumption'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE item_journal_batches DROP CONSTRAINT IF EXISTS item_journal_batches_default_entry_type_check;');

        DB::statement("
            ALTER TABLE item_journal_batches
            ADD CONSTRAINT item_journal_batches_default_entry_type_check
            CHECK (default_entry_type IN (
                'purchase',
                'sale',
                'positive_adjustment',
                'negative_adjustment',
                'transfer',
                'consumption',
                'output'
            ))
        ");
    }
};
