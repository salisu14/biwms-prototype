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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posting_transactions');
    }
};
