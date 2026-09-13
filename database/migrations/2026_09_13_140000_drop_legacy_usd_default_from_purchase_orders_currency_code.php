<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove the legacy database-level DEFAULT 'USD' from
     * purchase_orders.currency_code.
     *
     * Document currency is explicit business data. The approved application
     * creation paths resolve it (explicit choice -> vendor currency -> LCY/NGN)
     * before insert, so no database default may silently manufacture a USD
     * purchase order. The column stays NOT NULL: an insert that omits it must
     * fail rather than be defaulted (to USD or to any replacement such as NGN).
     *
     * Only the default is dropped; existing row values are not touched.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('purchase_orders', 'currency_code')) {
            return;
        }

        DB::statement('ALTER TABLE purchase_orders ALTER COLUMN currency_code DROP DEFAULT');
    }

    /**
     * Restore the legacy default on rollback (schema-only; does not change rows).
     */
    public function down(): void
    {
        if (! Schema::hasColumn('purchase_orders', 'currency_code')) {
            return;
        }

        DB::statement("ALTER TABLE purchase_orders ALTER COLUMN currency_code SET DEFAULT 'USD'");
    }
};
