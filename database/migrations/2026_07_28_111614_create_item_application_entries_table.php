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
        if (Schema::hasTable('item_application_entries')) {
            return;
        }

        Schema::create('item_application_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inbound_item_ledger_entry_id')->constrained('item_ledger_entries')->cascadeOnDelete();
            $table->foreignId('outbound_item_ledger_entry_id')->constrained('item_ledger_entries')->cascadeOnDelete();
            $table->decimal('applied_quantity', 18, 8);
            $table->decimal('remaining_quantity_after_application', 18, 8)->default(0);
            $table->date('application_date');
            $table->string('application_source', 80);
            $table->string('costing_method', 30);
            $table->decimal('unit_cost', 18, 8)->default(0);
            $table->decimal('cost_amount', 18, 4)->default(0);
            $table->boolean('is_reversed')->default(false);
            $table->foreignId('reversal_of_application_id')->nullable()->constrained('item_application_entries')->nullOnDelete();
            $table->string('idempotency_key', 128)->unique();
            $table->json('audit_metadata')->nullable();
            $table->timestamps();

            $table->index(['outbound_item_ledger_entry_id', 'is_reversed'], 'item_applications_outbound_active_index');
            $table->index(['inbound_item_ledger_entry_id', 'is_reversed'], 'item_applications_inbound_active_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_application_entries');
    }
};
