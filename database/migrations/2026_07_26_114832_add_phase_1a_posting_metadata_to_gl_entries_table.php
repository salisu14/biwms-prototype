<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->ensurePostingTransactionsTableExists();

        Schema::table('gl_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('gl_entries', 'posting_transaction_id')) {
                $table->foreignId('posting_transaction_id')->nullable()->after('id')->constrained('posting_transactions')->nullOnDelete();
            }

            if (! Schema::hasColumn('gl_entries', 'business_id')) {
                $table->foreignId('business_id')->nullable()->after('posting_transaction_id')->constrained('businesses')->nullOnDelete();
            }

            if (! Schema::hasColumn('gl_entries', 'source_module')) {
                $table->string('source_module', 50)->nullable()->after('business_id');
            }

            if (! Schema::hasColumn('gl_entries', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }

            if (! Schema::hasColumn('gl_entries', 'external_document_number')) {
                $table->string('external_document_number', 100)->nullable()->after('document_number');
            }

            if (! Schema::hasColumn('gl_entries', 'idempotency_key')) {
                $table->string('idempotency_key', 191)->nullable()->after('external_document_number');
            }

            if (! Schema::hasColumn('gl_entries', 'transaction_key')) {
                $table->string('transaction_key', 191)->nullable()->after('idempotency_key');
            }

            if (! Schema::hasColumn('gl_entries', 'posting_group_source')) {
                $table->string('posting_group_source', 100)->nullable()->after('transaction_key');
            }

            if (! Schema::hasColumn('gl_entries', 'cost_component')) {
                $table->string('cost_component', 50)->nullable()->after('posting_group_source');
            }

            if (! Schema::hasColumn('gl_entries', 'reversal_of_transaction_id')) {
                $table->foreignId('reversal_of_transaction_id')->nullable()->after('cost_component')->constrained('posting_transactions')->nullOnDelete();
            }
        });

        Schema::table('gl_entries', function (Blueprint $table) {
            $table->index(['posting_transaction_id'], 'gl_entries_posting_transaction_index');
            $table->index(['business_id', 'posting_date'], 'gl_entries_business_posting_date_index');
            $table->index(['source_module', 'source_type', 'source_id'], 'gl_entries_source_trace_index');
            $table->index(['idempotency_key'], 'gl_entries_idempotency_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gl_entries', function (Blueprint $table) {
            $table->dropIndex('gl_entries_posting_transaction_index');
            $table->dropIndex('gl_entries_business_posting_date_index');
            $table->dropIndex('gl_entries_source_trace_index');
            $table->dropIndex('gl_entries_idempotency_index');
        });

        Schema::table('gl_entries', function (Blueprint $table) {
            foreach ([
                'reversal_of_transaction_id',
                'posting_transaction_id',
                'business_id',
            ] as $column) {
                if (Schema::hasColumn('gl_entries', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach ([
                'source_module',
                'source_id',
                'external_document_number',
                'idempotency_key',
                'transaction_key',
                'posting_group_source',
                'cost_component',
            ] as $column) {
                if (Schema::hasColumn('gl_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function ensurePostingTransactionsTableExists(): void
    {
        if (Schema::hasTable('posting_transactions')) {
            return;
        }

        Schema::create('posting_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->string('source_module', 50);
            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_number', 100);
            $table->string('document_type', 100);
            $table->string('document_number', 100);
            $table->string('external_document_number', 100)->nullable();
            $table->string('transaction_key', 191);
            $table->string('idempotency_key', 191)->unique();
            $table->unsignedBigInteger('transaction_number')->nullable()->unique();
            $table->date('posting_date');
            $table->date('document_date')->nullable();
            $table->string('currency_code', 10)->default('NGN');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->json('dimensions')->nullable();
            $table->string('status', 30)->default('completed');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_of_transaction_id')->nullable()->constrained('posting_transactions')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['source_module', 'source_type', 'source_id']);
            $table->index(['document_type', 'document_number']);
            $table->index(['business_id', 'posting_date']);
            $table->index(['status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('create sequence if not exists gl_entry_number_seq');
            DB::statement('create sequence if not exists gl_transaction_number_seq');
            DB::statement("select setval('gl_entry_number_seq', greatest(coalesce((select max(entry_number) from gl_entries), 0), 0) + 1, false)");
            DB::statement("select setval('gl_transaction_number_seq', greatest(coalesce((select max(transaction_number) from gl_entries), 0), 0) + 1, false)");
        }
    }
};
