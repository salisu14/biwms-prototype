<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('value_entries')) {
            return;
        }

        Schema::table('value_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('value_entries', 'business_id')) {
                $table->foreignId('business_id')->nullable()->after('id')->constrained('businesses')->nullOnDelete();
            }

            if (! Schema::hasColumn('value_entries', 'source_module')) {
                $table->string('source_module', 50)->nullable()->after('source_type');
            }

            if (! Schema::hasColumn('value_entries', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_no');
            }

            if (! Schema::hasColumn('value_entries', 'source_number')) {
                $table->string('source_number', 100)->nullable()->after('source_id');
            }

            if (! Schema::hasColumn('value_entries', 'cost_component')) {
                $table->string('cost_component', 50)->nullable()->after('costing_method');
            }

            if (! Schema::hasColumn('value_entries', 'value_entry_state')) {
                $table->string('value_entry_state', 30)->default('actual')->after('cost_component');
            }

            if (! Schema::hasColumn('value_entries', 'valued_quantity')) {
                $table->decimal('valued_quantity', 18, 8)->default(0)->after('invoiced_quantity');
            }

            if (! Schema::hasColumn('value_entries', 'remaining_quantity')) {
                $table->decimal('remaining_quantity', 18, 8)->default(0)->after('valued_quantity');
            }

            if (! Schema::hasColumn('value_entries', 'posting_transaction_id')) {
                $table->foreignId('posting_transaction_id')->nullable()->after('gl_posted')->constrained('posting_transactions')->nullOnDelete();
            }

            if (! Schema::hasColumn('value_entries', 'gl_posted_at')) {
                $table->timestamp('gl_posted_at')->nullable()->after('gl_posting_date');
            }

            if (! Schema::hasColumn('value_entries', 'reversal_of_value_entry_id')) {
                $table->foreignId('reversal_of_value_entry_id')->nullable()->after('original_entry_no')->constrained('value_entries')->nullOnDelete();
            }

            if (! Schema::hasColumn('value_entries', 'idempotency_key')) {
                $table->string('idempotency_key', 128)->nullable()->after('reversal_of_value_entry_id');
            }

            if (! Schema::hasColumn('value_entries', 'accounting_metadata')) {
                $table->json('accounting_metadata')->nullable()->after('idempotency_key');
            }
        });

        Schema::table('value_entries', function (Blueprint $table): void {
            if (! Schema::hasIndex('value_entries', 'value_entries_posting_transaction_index')) {
                $table->index(['posting_transaction_id'], 'value_entries_posting_transaction_index');
            }

            if (! Schema::hasIndex('value_entries', 'value_entries_document_gl_index')) {
                $table->index(['document_type', 'document_no', 'gl_posted'], 'value_entries_document_gl_index');
            }

            if (! Schema::hasIndex('value_entries', 'value_entries_source_index')) {
                $table->index(['source_module', 'source_type', 'source_id'], 'value_entries_source_index');
            }

            if (! Schema::hasIndex('value_entries', 'value_entries_idempotency_key_unique')) {
                $table->unique(['idempotency_key'], 'value_entries_idempotency_key_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('value_entries')) {
            return;
        }

        Schema::table('value_entries', function (Blueprint $table): void {
            if (Schema::hasIndex('value_entries', 'value_entries_idempotency_key_unique')) {
                $table->dropUnique('value_entries_idempotency_key_unique');
            }

            if (Schema::hasIndex('value_entries', 'value_entries_source_index')) {
                $table->dropIndex('value_entries_source_index');
            }

            if (Schema::hasIndex('value_entries', 'value_entries_document_gl_index')) {
                $table->dropIndex('value_entries_document_gl_index');
            }

            if (Schema::hasIndex('value_entries', 'value_entries_posting_transaction_index')) {
                $table->dropIndex('value_entries_posting_transaction_index');
            }
        });

        Schema::table('value_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reversal_of_value_entry_id');
            $table->dropConstrainedForeignId('posting_transaction_id');
            $table->dropConstrainedForeignId('business_id');
            $table->dropColumn([
                'source_module',
                'source_id',
                'source_number',
                'cost_component',
                'value_entry_state',
                'valued_quantity',
                'remaining_quantity',
                'gl_posted_at',
                'idempotency_key',
                'accounting_metadata',
            ]);
        });
    }
};
