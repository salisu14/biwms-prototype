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

        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status::text = ANY (ARRAY[
            'PENDING',
            'SUBMITTED',
            'APPROVED',
            'POSTED',
            'CLEARED',
            'RECONCILED',
            'VOIDED',
            'RETURNED'
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

        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status::text = ANY (ARRAY[
            'PENDING',
            'POSTED',
            'CLEARED',
            'RECONCILED',
            'VOIDED',
            'RETURNED'
        ]::text[]))");
    }
};
