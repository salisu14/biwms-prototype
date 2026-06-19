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
        Schema::table('blanket_orders', function (Blueprint $table) {
            $table->string('order_type', 20)->default('Purchase')->after('document_number'); // Purchase, Sales
            $table->foreignId('customer_id')->nullable()->after('vendor_id')->constrained('customers')->nullOnDelete();
            $table->unsignedBigInteger('vendor_id')->nullable()->change(); // Allow null if it's a Sales order

            // Sell-to Address
            $table->string('sell_to_customer_no', 20)->nullable()->after('buy_from_contact');
            $table->string('sell_to_customer_name', 100)->nullable();
            $table->string('sell_to_address', 100)->nullable();
            $table->string('sell_to_address_2', 50)->nullable();
            $table->string('sell_to_city', 30)->nullable();
            $table->string('sell_to_post_code', 20)->nullable();
            $table->string('sell_to_county', 30)->nullable();
            $table->string('sell_to_country_region_code', 10)->nullable();
            $table->string('sell_to_contact', 100)->nullable();

            // Bill-to Address
            $table->string('bill_to_customer_no', 20)->nullable();
            $table->string('bill_to_name', 100)->nullable();
            $table->string('bill_to_address', 100)->nullable();
            $table->string('bill_to_address_2', 50)->nullable();
            $table->string('bill_to_city', 30)->nullable();
            $table->string('bill_to_post_code', 20)->nullable();
            $table->string('bill_to_county', 30)->nullable();
            $table->string('bill_to_country_region_code', 10)->nullable();
            $table->string('bill_to_contact', 100)->nullable();

            $table->string('salesperson_code', 20)->nullable();
        });

        Schema::table('blanket_order_lines', function (Blueprint $table) {
            $table->decimal('unit_price', 19, 4)->nullable();
            $table->decimal('quantity_shipped', 19, 4)->default(0);
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blanket_order_lines', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'quantity_shipped', 'sales_order_id', 'sales_order_line_id']);
        });

        Schema::table('blanket_orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_type', 'customer_id',
                'sell_to_customer_no', 'sell_to_customer_name', 'sell_to_address', 'sell_to_address_2', 'sell_to_city', 'sell_to_post_code', 'sell_to_county', 'sell_to_country_region_code', 'sell_to_contact',
                'bill_to_customer_no', 'bill_to_name', 'bill_to_address', 'bill_to_address_2', 'bill_to_city', 'bill_to_post_code', 'bill_to_county', 'bill_to_country_region_code', 'bill_to_contact',
                'salesperson_code',
            ]);
            $table->foreignId('vendor_id')->nullable(false)->change();
        });
    }
};
