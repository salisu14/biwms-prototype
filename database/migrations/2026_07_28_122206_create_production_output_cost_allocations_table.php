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
        if (Schema::hasTable('production_output_cost_allocations')) {
            return;
        }

        Schema::create('production_output_cost_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('output_item_ledger_entry_id')->constrained('item_ledger_entries')->restrictOnDelete();
            $table->foreignId('output_value_entry_id')->nullable()->constrained('value_entries')->nullOnDelete();
            $table->decimal('output_quantity', 18, 8);
            $table->decimal('eligible_cost_before_allocation', 18, 4)->default(0);
            $table->decimal('allocated_material_cost', 18, 4)->default(0);
            $table->decimal('allocated_capacity_cost', 18, 4)->default(0);
            $table->decimal('allocated_overhead_cost', 18, 4)->default(0);
            $table->decimal('allocated_total_cost', 18, 4)->default(0);
            $table->string('allocation_status', 30)->default('pending');
            $table->boolean('is_final_allocation')->default(false);
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_allocation_id')->nullable()->constrained('production_output_cost_allocations')->nullOnDelete();
            $table->string('idempotency_key', 128)->unique();
            $table->string('source_identity_key', 128)->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['production_order_id', 'output_item_ledger_entry_id'], 'production_output_allocations_order_entry_index');
            $table->index(['production_order_id', 'allocation_status'], 'production_output_allocations_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_output_cost_allocations');
    }
};
