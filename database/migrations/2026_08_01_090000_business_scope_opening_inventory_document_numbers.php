<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('opening_inventories')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE opening_inventories DROP CONSTRAINT IF EXISTS opening_inventories_document_number_unique');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS opening_inventories_business_document_number_unique ON opening_inventories (COALESCE(business_id, 0), document_number)');

            return;
        }

        Schema::table('opening_inventories', function ($table): void {
            try {
                $table->dropUnique('opening_inventories_document_number_unique');
            } catch (Throwable) {
                //
            }

            $table->unique(['business_id', 'document_number'], 'opening_inventories_business_document_number_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('opening_inventories')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS opening_inventories_business_document_number_unique');
            DB::statement('ALTER TABLE opening_inventories ADD CONSTRAINT opening_inventories_document_number_unique UNIQUE (document_number)');

            return;
        }

        Schema::table('opening_inventories', function ($table): void {
            try {
                $table->dropUnique('opening_inventories_business_document_number_unique');
            } catch (Throwable) {
                //
            }

            $table->unique('document_number', 'opening_inventories_document_number_unique');
        });
    }
};
