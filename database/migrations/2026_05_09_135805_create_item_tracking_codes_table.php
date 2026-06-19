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
        Schema::create('item_tracking_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description');

            // Serial No. tracking
            $table->boolean('snspecific_tracking')->default(false)->comment('SN specific tracking');

            // Lot tracking
            $table->boolean('lotspecific_tracking')->default(false)->comment('Lot specific tracking');
            $table->boolean('lot_wholesale_tracking')->default(false)->comment('Lot wholesale tracking');

            // Expiration
            $table->boolean('man_expiration_date_entry_reqd')->default(false);
            $table->boolean('man_expiration_date_on_receipt')->default(false);
            $table->boolean('strict_expiration_posting')->default(false);
            $table->boolean('allow_expiration_correction')->default(false);

            // Lot info required
            $table->boolean('lot_info_purchase_inbound')->default(false);
            $table->boolean('lot_info_purchase_outbound')->default(false);
            $table->boolean('lot_info_sales_inbound')->default(false);
            $table->boolean('lot_info_sales_outbound')->default(false);

            // SN info required
            $table->boolean('sn_info_purchase_inbound')->default(false);
            $table->boolean('sn_info_purchase_outbound')->default(false);
            $table->boolean('sn_info_sales_inbound')->default(false);
            $table->boolean('sn_info_sales_outbound')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_tracking_codes');
    }
};
