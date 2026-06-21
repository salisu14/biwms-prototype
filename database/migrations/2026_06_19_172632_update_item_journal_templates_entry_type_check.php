<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop the old restrictive constraint
        DB::statement('ALTER TABLE item_journal_templates DROP CONSTRAINT IF EXISTS item_journal_templates_default_entry_type_check;');

        // 2. Add the new constraint including 'prod_consumption'
        // NOTE: Make sure all values from your JournalLineType enum are listed here!
        DB::statement("
            ALTER TABLE item_journal_templates
            ADD CONSTRAINT item_journal_templates_default_entry_type_check
            CHECK (default_entry_type IN (
                'purchase',
                'sale',
                'positive_adjmt',
                'negative_adjmt',
                'transfer',
                'consumption',
                'output',
                'prod_consumption'
            ))
        ");
    }

    public function down(): void
    {
        // Revert back to the old constraint (without prod_consumption)
        DB::statement('ALTER TABLE item_journal_templates DROP CONSTRAINT IF EXISTS item_journal_templates_default_entry_type_check;');

        DB::statement("
            ALTER TABLE item_journal_templates
            ADD CONSTRAINT item_journal_templates_default_entry_type_check
            CHECK (default_entry_type IN (
                'purchase',
                'sale',
                'positive_adjmt',
                'negative_adjmt',
                'transfer',
                'consumption',
                'output'
            ))
        ");
    }
};
