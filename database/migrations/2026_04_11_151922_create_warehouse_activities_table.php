<?php

use App\Enums\WarehouseActivityType;
use App\Enums\WarehouseDocumentStatus;
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
        Schema::create('warehouse_activities', function (Blueprint $table) {
            $table->id();
            $table->string('no', 50)->unique();
            $table->enum('activity_type', WarehouseActivityType::cases());
            $table->enum('status', WarehouseDocumentStatus::cases())->default('open');
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bins')->nullOnDelete();

            // Source document references
            $table->string('source_document', 50)->nullable(); // production_order, sales_order, purchase_order, transfer_order
            $table->string('source_no', 50)->nullable();
            $table->integer('source_line_no')->nullable();
            $table->foreignId('source_id')->nullable(); // polymorphic reference to source document

            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['activity_type', 'status', 'location_id']);
            $table->index(['source_document', 'source_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_activities');
    }
};
