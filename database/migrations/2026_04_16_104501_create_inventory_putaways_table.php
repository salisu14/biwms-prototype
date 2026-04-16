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
        // 4. Inventory Put-Aways (Basic level)
        Schema::create('inventory_putaways', function (Blueprint $table) {
            $table->id();
            $table->string('no', 50)->unique();
            $table->foreignId('location_id')->constrained();
            $table->enum('source_document', ['Purchase Order', 'Sales Return', 'Inbound Transfer', 'Production Output', 'Assembly Output']);
            $table->string('source_no', 50);
            $table->foreignId('assigned_user_id')->nullable()->constrained('users');
            $table->enum('status', ['Open', 'Pending', 'Completed'])->default('Open');
            $table->timestamp('posting_date')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_putaway_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_putaway_id')->constrained()->onDelete('cascade');
            $table->integer('line_no');
            $table->foreignId('item_id')->constrained();
            $table->foreignId('bin_id')->nullable()->constrained(); // Destination bin
            $table->decimal('quantity', 15, 4);
            $table->decimal('qty_to_handle', 15, 4)->default(0);
            $table->decimal('qty_handled', 15, 4)->default(0);
            $table->string('unit_of_measure', 20);
            $table->text('item_tracking')->nullable(); // JSON for lot/serial
            $table->timestamps();
        });

        // 5. Warehouse Put-Aways (Advanced level)
        Schema::create('warehouse_putaways', function (Blueprint $table) {
            $table->id();
            $table->string('no', 50)->unique();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('warehouse_receipt_id')->nullable()->constrained(); // From posted receipt
            $table->foreignId('assigned_user_id')->nullable()->constrained('users');
            $table->enum('status', ['Open', 'In Progress', 'Completed'])->default('Open');
            $table->enum('sorting_method', ['Item', 'Bin Ranking', 'Document', 'Due Date'])->nullable();
            $table->timestamps();
        });

        Schema::create('warehouse_putaway_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_putaway_id')->constrained()->onDelete('cascade');
            $table->integer('line_no');
            $table->enum('action_type', ['Take', 'Place']); // BC uses Take/Place lines [^25^]
            $table->foreignId('item_id')->constrained();
            $table->foreignId('bin_id')->constrained();
            $table->foreignId('zone_id')->nullable();
            $table->decimal('quantity', 15, 4);
            $table->decimal('qty_to_handle', 15, 4)->default(0);
            $table->decimal('qty_handled', 15, 4)->default(0);
            $table->string('unit_of_measure', 20);
            $table->enum('source_document', ['Purchase Order', 'Sales Return', 'Inbound Transfer', 'Internal Put-away']);
            $table->string('source_no', 50);
            $table->integer('source_line_no');
            $table->boolean('breakbulk')->default(false); // For unit of measure conversion
            $table->text('item_tracking')->nullable();
            $table->timestamps();
        });

        // 6. Put-away Worksheet (For bulk creation)
        Schema::create('putaway_worksheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->enum('status', ['Open', 'Processed'])->default('Open');
            $table->timestamps();
        });

        Schema::create('putaway_worksheet_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('putaway_worksheet_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_receipt_id')->constrained();
            $table->foreignId('item_id')->constrained();
            $table->decimal('quantity', 15, 4);
            $table->decimal('qty_to_handle', 15, 4)->default(0);
            $table->string('source_no', 50);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('putaway_worksheet_lines');
        Schema::dropIfExists('inventory_putaway_lines');
        Schema::dropIfExists('putaway_worksheets');
        Schema::dropIfExists('inventory_putaways');
    }
};
