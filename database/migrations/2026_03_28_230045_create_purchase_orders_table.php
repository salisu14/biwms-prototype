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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            // Document identification
            $table->string('order_number', 50)->unique();

            // Using string for enum values
            $table->string('order_type', 30)->default('purchase_order');
            $table->string('status', 20)->default('PENDING');

            // Vendor info
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->string('vendor_name', 255);

            // Dates
            $table->date('order_date');
            $table->foreignId('location_id')->constrained('locations');
            $table->date('posting_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('delivery_date')->nullable();

            // Terms
            $table->string('payment_terms', 50)->nullable();
            $table->text('comment')->nullable();

            // Financial totals
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->decimal('total_vat', 15, 4)->default(0);
            $table->decimal('grand_total', 15, 4)->default(0);

            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('order_number');
            $table->index('order_type');
            $table->index('status');
            $table->index(['order_type', 'status']);
            $table->index('vendor_id');
            $table->index('order_date');

            // Posting Groups (copied from vendor on create)
            $table->foreignId('general_business_posting_group_id')
                ->nullable()
                ->after('vendor_id')
                ->constrained('general_business_posting_groups');

            $table->foreignId('vendor_posting_group_id')
                ->nullable()
                ->after('general_business_posting_group_id')
                ->constrained('vendor_posting_groups');

            $table->string('vat_bus_posting_group', 20)->nullable()->after('vendor_posting_group_id');

            // For tracking receipt/invoice status
            $table->timestamp('fully_received_at')->nullable()->after('approved_at');
            $table->timestamp('fully_invoiced_at')->nullable()->after('fully_received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
