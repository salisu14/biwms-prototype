<?php

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
        // Production Orders
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique();
            $table->string('status')->default('SIMULATED');
            $table->string('source_type')->nullable(); // ITEM, FAMILY, SALES_HEADER
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_no')->nullable();
            $table->text('description')->nullable();

            $table->foreignId('item_id')->constrained('items');
            $table->string('variant_code')->nullable();
            $table->decimal('quantity', 15, 4);
            $table->string('unit_of_measure_code')->nullable();
            $table->decimal('quantity_base', 15, 4)->default(0);

            $table->date('due_date')->nullable();
            $table->dateTime('starting_date_time')->nullable();
            $table->dateTime('ending_date_time')->nullable();

            $table->foreignId('inventory_posting_group_id')->nullable()->constrained('inventory_posting_groups');
            $table->foreignId('general_product_posting_group_id')->nullable()->constrained('general_product_posting_groups');

            $table->foreignId('production_bom_id')->nullable()->constrained('production_boms');
            $table->foreignId('routing_id')->nullable()->constrained('routings');
            $table->foreignId('production_bom_version_id')->nullable();
            $table->foreignId('routing_version_id')->nullable();

            $table->string('location_code')->nullable();
            $table->string('bin_code')->nullable();

            $table->string('shortcut_dimension_1_code')->nullable();
            $table->string('shortcut_dimension_2_code')->nullable();
            $table->unsignedBigInteger('dimension_set_id')->nullable();

            $table->string('costing_method')->default('STANDARD'); // STANDARD, FIFO, LIFO, AVERAGE, SPECIFIC
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('cost_rollup', 15, 4)->default(0);

            $table->string('flushing_method')->default('MANUAL'); // MANUAL, FORWARD, BACKWARD, PICK+BACKWARD, PICK+FORWARD
            $table->decimal('scrap_percent', 5, 2)->default(0);

            $table->integer('planning_level')->default(0);
            $table->integer('priority')->default(0);

            $table->boolean('posted')->default(false);
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users');

            $table->timestamp('finished_at')->nullable();
            $table->foreignId('finished_by')->nullable()->constrained('users');

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('last_modified_by')->nullable()->constrained('users');

            $table->foreignId('general_business_posting_group_id')
                ->after('inventory_posting_group_id')
                ->nullable()
                ->constrained('general_business_posting_groups');

            $table->boolean('reserved_from_stock')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'due_date']);
            $table->index(['item_id', 'status']);
            $table->index(['source_id', 'source_type']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
