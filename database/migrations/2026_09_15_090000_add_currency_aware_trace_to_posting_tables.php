<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3C-B currency-aware posting boundary trace.
 *
 * Purely additive and nullable: existing rows keep NULL and are never
 * reinterpreted or backfilled. gl_entries.debit_amount/credit_amount/amount and
 * their *_lcy twins remain LCY; these columns carry the optional
 * document-currency trace for CURRENCY_AWARE postings only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('gl_entries', 'document_currency_code')) {
                $table->string('document_currency_code', 3)->nullable()->after('exchange_rate');
            }

            if (! Schema::hasColumn('gl_entries', 'document_debit_amount')) {
                $table->decimal('document_debit_amount', 20, 4)->nullable()->after('document_currency_code');
            }

            if (! Schema::hasColumn('gl_entries', 'document_credit_amount')) {
                $table->decimal('document_credit_amount', 20, 4)->nullable()->after('document_debit_amount');
            }

            if (! Schema::hasColumn('gl_entries', 'document_amount')) {
                $table->decimal('document_amount', 20, 4)->nullable()->after('document_credit_amount');
            }

            if (! Schema::hasColumn('gl_entries', 'currency_factor')) {
                $table->decimal('currency_factor', 20, 6)->nullable()->after('document_amount');
            }

            if (! Schema::hasColumn('gl_entries', 'posting_line_type')) {
                $table->string('posting_line_type', 30)->nullable()->after('currency_factor');
            }

            if (! Schema::hasColumn('gl_entries', 'lcy_only_reason')) {
                $table->string('lcy_only_reason', 30)->nullable()->after('posting_line_type');
            }
        });

        Schema::table('posting_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('posting_transactions', 'economic_fingerprint')) {
                $table->string('economic_fingerprint', 64)->nullable()->after('exchange_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gl_entries', function (Blueprint $table) {
            foreach ([
                'lcy_only_reason',
                'posting_line_type',
                'currency_factor',
                'document_amount',
                'document_credit_amount',
                'document_debit_amount',
                'document_currency_code',
            ] as $column) {
                if (Schema::hasColumn('gl_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('posting_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('posting_transactions', 'economic_fingerprint')) {
                $table->dropColumn('economic_fingerprint');
            }
        });
    }
};
