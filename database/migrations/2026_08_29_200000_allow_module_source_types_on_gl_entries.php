<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gl_entries') || DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('alter table gl_entries drop constraint if exists gl_entries_source_type_check');
        DB::statement("alter table gl_entries add constraint gl_entries_source_type_check check (source_type in ('CUSTOMER', 'VENDOR', 'ITEM', 'BANK', 'FIXED_ASSET', 'EMPLOYEE', 'COMMISSION', 'PETTY_CASH', 'GENERAL_JOURNAL'))");
    }

    public function down(): void
    {
        if (! Schema::hasTable('gl_entries') || DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('alter table gl_entries drop constraint if exists gl_entries_source_type_check');
        DB::statement("alter table gl_entries add constraint gl_entries_source_type_check check (source_type in ('CUSTOMER', 'VENDOR', 'ITEM', 'BANK', 'FIXED_ASSET', 'EMPLOYEE', 'GENERAL_JOURNAL'))");
    }
};
