<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE inventory_adjustment_journals DROP CONSTRAINT IF EXISTS inventory_adjustment_journals_status_check');
        DB::statement("ALTER TABLE inventory_adjustment_journals ADD CONSTRAINT inventory_adjustment_journals_status_check CHECK (status::text = ANY (ARRAY[
            'Open',
            'Submitted',
            'Released',
            'Posted',
            'Cancelled'
        ]::text[]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE inventory_adjustment_journals DROP CONSTRAINT IF EXISTS inventory_adjustment_journals_status_check');
        DB::statement("ALTER TABLE inventory_adjustment_journals ADD CONSTRAINT inventory_adjustment_journals_status_check CHECK (status::text = ANY (ARRAY[
            'Open',
            'Released',
            'Posted'
        ]::text[]))");
    }
};
