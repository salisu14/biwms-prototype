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
        Schema::create('approval_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('description');
            $table->boolean('enabled')->default(true);
            $table->string('document_type', 30); // 'purchase_quote', 'purchase_order', etc.
            $table->decimal('amount_limit', 18, 4)->nullable(); // Max amount this template applies to
            $table->foreignId('vendor_posting_group_filter')->nullable()->constrained('vendor_posting_groups');
            $table->json('dimension_1_filter')->nullable();
            $table->json('dimension_2_filter')->nullable();
            $table->string('location_filter', 10)->nullable();
            $table->integer('due_date_formula')->nullable(); // Days to approve
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_templates');
    }
};
