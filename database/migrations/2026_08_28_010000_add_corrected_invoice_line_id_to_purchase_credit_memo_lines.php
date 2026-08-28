<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureCorrectedInvoiceLineReference(
            table: 'purchase_credit_memo_lines',
            afterColumn: 'description',
            dropColumnOnDown: true
        );

        $this->ensureCorrectedInvoiceLineReference(
            table: 'posted_purchase_credit_memo_lines',
            afterColumn: null,
            dropColumnOnDown: false
        );
    }

    public function down(): void
    {
        $this->dropCorrectedInvoiceLineReference('purchase_credit_memo_lines', true);
        $this->dropCorrectedInvoiceLineReference('posted_purchase_credit_memo_lines', false);
    }

    private function ensureCorrectedInvoiceLineReference(string $table, ?string $afterColumn, bool $dropColumnOnDown): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, 'corrected_invoice_line_id')) {
            Schema::table($table, function (Blueprint $blueprint) use ($afterColumn): void {
                $column = $blueprint->foreignId('corrected_invoice_line_id')
                    ->nullable()
                    ->constrained('posted_purchase_invoice_lines')
                    ->nullOnDelete();

                if ($afterColumn) {
                    $column->after($afterColumn);
                }
            });

            return;
        }

        $metadata = $this->foreignKeyMetadata($table, 'corrected_invoice_line_id');

        if (! $metadata) {
            return;
        }

        if ($metadata->referenced_table !== 'posted_purchase_invoice_lines') {
            Schema::table($table, function (Blueprint $blueprint) use ($metadata): void {
                $blueprint->dropForeign($metadata->constraint_name);
                $blueprint->foreign('corrected_invoice_line_id', $metadata->constraint_name)
                    ->nullable()
                    ->references('id')
                    ->on('posted_purchase_invoice_lines')
                    ->nullOnDelete();
            });
        }
    }

    private function dropCorrectedInvoiceLineReference(string $table, bool $dropColumn): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'corrected_invoice_line_id')) {
            return;
        }

        $metadata = $this->foreignKeyMetadata($table, 'corrected_invoice_line_id');

        Schema::table($table, function (Blueprint $blueprint) use ($dropColumn, $metadata): void {
            if ($metadata?->constraint_name) {
                $blueprint->dropForeign($metadata->constraint_name);
            }

            if ($dropColumn) {
                $blueprint->dropColumn('corrected_invoice_line_id');
            }
        });
    }

    private function foreignKeyMetadata(string $table, string $column): ?object
    {
        return DB::selectOne(
            <<<'SQL'
                select
                    c.conname as constraint_name,
                    ref_rel.relname as referenced_table
                from pg_constraint c
                join pg_class rel on rel.oid = c.conrelid
                join pg_namespace ns on ns.oid = rel.relnamespace
                join pg_class ref_rel on ref_rel.oid = c.confrelid
                join lateral unnest(c.conkey) with ordinality as key_cols(attnum, ordinality) on true
                join pg_attribute att on att.attrelid = rel.oid and att.attnum = key_cols.attnum
                where c.contype = 'f'
                  and ns.nspname = current_schema()
                  and rel.relname = ?
                  and att.attname = ?
                limit 1
            SQL,
            [$table, $column]
        );
    }
};
