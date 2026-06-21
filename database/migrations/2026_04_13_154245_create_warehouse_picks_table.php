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
        Schema::create('warehouse_picks', function (Blueprint $table) {
            $table->id();
            $table->string('no', 50)->unique();
            $table->string('status', 30)->default('open');
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_document', 50)->nullable();
            $table->string('source_no', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('warehouse_shipment_id')->nullable()->constrained('warehouse_shipments')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['status', 'location_id']);
            $table->index(['source_document', 'source_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_picks');
    }
};
