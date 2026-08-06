<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE categories
             DROP CONSTRAINT IF EXISTS categories_category_type_check'
        );

        DB::statement(
            "ALTER TABLE categories
             ADD CONSTRAINT categories_category_type_check
             CHECK (
                 category_type IN (
                     'FINISHED_GOOD',
                     'SEMI_FINISHED',
                     'RAW_MATERIAL',
                     'PACKAGING',
                     'CONSUMABLE',
                     'SPARE_PART'
                 )
             )"
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE categories
             DROP CONSTRAINT IF EXISTS categories_category_type_check'
        );

        /*
         * A safe rollback allows both historical and operational values.
         *
         * Restoring only the legacy constraint would fail if operational
         * category records already exist.
         */
        DB::statement(
            "ALTER TABLE categories
             ADD CONSTRAINT categories_category_type_check
             CHECK (
                 category_type IN (
                     'THERAPEUTIC',
                     'BOTANICAL',
                     'REGULATORY',
                     'FORM',
                     'SOURCE',
                     'PROCESSING',
                     'FINISHED_GOOD',
                     'SEMI_FINISHED',
                     'RAW_MATERIAL',
                     'PACKAGING',
                     'CONSUMABLE',
                     'SPARE_PART'
                 )
             )"
        );
    }
};
