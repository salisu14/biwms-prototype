<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_invoices')) {
            Schema::table('purchase_invoices', function (Blueprint $table): void {
                if (! Schema::hasColumn('purchase_invoices', 'status')) {
                    $table->string('status', 20)->default('draft')->after('due_date');
                    $table->index('status');
                }

                if (! Schema::hasColumn('purchase_invoices', 'approved_by')) {
                    $table->foreignId('approved_by')->nullable()->after('paid_in_full_date')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('purchase_invoices', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by');
                }

                if (! Schema::hasColumn('purchase_invoices', 'rejected_by')) {
                    $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('purchase_invoices', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable()->after('rejected_by');
                }
            });
        }

        if (! Schema::hasTable('posted_purchase_invoices')) {
            Schema::create('posted_purchase_invoices', function (Blueprint $table): void {
                $table->id();
                $table->string('document_number', 20)->unique();
                $table->string('external_document_number', 50)->nullable();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->string('order_number', 20)->nullable();
                $table->foreignId('vendor_id')->constrained('vendors');
                $table->string('vendor_name', 100);
                $table->string('vendor_address', 200)->nullable();
                $table->foreignId('general_business_posting_group_id')->nullable()->constrained('general_business_posting_groups');
                $table->foreignId('vendor_posting_group_id')->nullable()->constrained('vendor_posting_groups');
                $table->foreignId('vat_business_posting_group_id')->nullable()->constrained('vat_business_posting_groups');
                $table->foreignId('location_id')->nullable()->constrained('locations');
                $table->date('posting_date');
                $table->date('document_date');
                $table->date('due_date');
                $table->date('vat_date')->nullable();
                $table->decimal('total_amount', 15, 4)->default(0);
                $table->decimal('total_vat', 15, 4)->default(0);
                $table->decimal('grand_total', 15, 4)->default(0);
                $table->string('currency_code', 3)->default('USD');
                $table->decimal('currency_factor', 15, 6)->default(1);
                $table->decimal('amount_paid', 15, 4)->default(0);
                $table->decimal('remaining_amount', 15, 4)->default(0);
                $table->boolean('paid_in_full')->default(false);
                $table->timestamp('paid_in_full_date')->nullable();
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->boolean('cancelled')->default(false);
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('cancellation_reason', 200)->nullable();
                $table->string('corrective_document_number', 20)->nullable();
                $table->json('dimensions')->nullable();
                $table->timestamps();

                $table->index(['vendor_id', 'posting_date']);
                $table->index(['order_id', 'posting_date']);
            });
        }

        if (! Schema::hasTable('posted_purchase_invoice_lines')) {
            Schema::create('posted_purchase_invoice_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('posted_purchase_invoice_id')->constrained('posted_purchase_invoices')->cascadeOnDelete();
                $table->unsignedBigInteger('po_line_id')->nullable();
                $table->integer('po_line_number')->nullable();
                $table->foreignId('item_id')->nullable()->constrained('items');
                $table->string('item_code', 20)->nullable();
                $table->string('item_description', 100);
                $table->string('variant_code', 20)->nullable();
                $table->foreignId('general_product_posting_group_id')->nullable()->constrained('general_product_posting_groups');
                $table->foreignId('inventory_posting_group_id')->nullable()->constrained('inventory_posting_groups');
                $table->foreignId('gl_account_id')->nullable()->constrained('chart_of_accounts');
                $table->string('gl_account_number', 20)->nullable();
                $table->string('gl_account_name', 100)->nullable();
                $table->decimal('quantity', 15, 4)->default(0);
                $table->string('unit_of_measure_code', 20)->nullable();
                $table->decimal('qty_per_unit_of_measure', 10, 4)->default(1);
                $table->decimal('quantity_base', 15, 4)->default(0);
                $table->decimal('unit_cost', 15, 4)->default(0);
                $table->decimal('unit_cost_lcy', 15, 4)->default(0);
                $table->decimal('line_total', 15, 4)->default(0);
                $table->decimal('line_discount_amount', 15, 4)->default(0);
                $table->decimal('line_discount_percent', 5, 2)->default(0);
                $table->string('vat_code', 20)->nullable();
                $table->decimal('vat_percentage', 5, 2)->default(0);
                $table->decimal('vat_amount', 15, 4)->default(0);
                $table->decimal('vat_amount_lcy', 15, 4)->default(0);
                $table->decimal('amount_including_vat', 15, 4)->default(0);
                $table->decimal('amount_including_vat_lcy', 15, 4)->default(0);
                $table->string('lot_number', 50)->nullable();
                $table->string('serial_number', 50)->nullable();
                $table->date('expiration_date')->nullable();
                $table->json('dimensions')->nullable();
                $table->unsignedBigInteger('item_ledger_entry_id')->nullable();
                $table->unsignedBigInteger('gl_entry_id')->nullable();
                $table->integer('line_number')->default(0);
                $table->date('posting_date')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_invoices')) {
            Schema::table('purchase_invoices', function (Blueprint $table): void {
                foreach (['approved_by', 'rejected_by'] as $fk) {
                    if (Schema::hasColumn('purchase_invoices', $fk)) {
                        $table->dropConstrainedForeignId($fk);
                    }
                }

                foreach (['approved_at', 'rejected_at', 'status'] as $column) {
                    if (Schema::hasColumn('purchase_invoices', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('posted_purchase_invoice_lines');
        Schema::dropIfExists('posted_purchase_invoices');
    }
};
