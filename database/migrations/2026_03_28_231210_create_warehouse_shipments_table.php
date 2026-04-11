<?php

use App\Enums\ShipmentStatus;
use App\Enums\SourceDocument;
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
        Schema::create('warehouse_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 20)->unique();
            $table->foreignId('location_id')->constrained('locations');

            // Source Document
            $table->enum('source_document', array_column(SourceDocument::cases(), 'value'));

            $table->unsignedBigInteger('source_document_id');
            $table->string('source_document_number', 20);

            // Customer (if applicable)
            $table->foreignId('customer_id')->nullable()->constrained('customers');

            // Shipping
            $table->string('shipping_agent_code', 20)->nullable();
            $table->string('shipping_agent_service_code', 20)->nullable();
            $table->string('external_document_number', 50)->nullable(); // Customer PO

            // Status
            // Source Document
            $table->enum('status', array_column(ShipmentStatus::cases(), 'value'))
                ->default('OPEN');

            // Assignment
            $table->foreignId('assigned_user_id')->nullable();

            // Dates
            $table->date('shipment_date');
            $table->date('planned_delivery_date')->nullable();
            $table->timestamp('posted_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_shipments');
    }
};
