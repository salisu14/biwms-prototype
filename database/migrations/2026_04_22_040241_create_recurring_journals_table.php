<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Legacy migration superseded by 2026_04_11_210151_create_recurring_journal_templates_table.
 *
 * The recurring_journal_lines table is already created by the above migration
 * with the BC-standard schema (batch_id → recurring_journal_batches).
 * This migration previously attempted to create a duplicate with an incorrect
 * journal_line_id FK pattern and is now a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        // No-op: recurring_journal_lines already created by
        // 2026_04_11_210151_create_recurring_journal_templates_table
    }

    public function down(): void
    {
        // No-op
    }
};
