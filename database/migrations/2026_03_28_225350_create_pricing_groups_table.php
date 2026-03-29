<?php

use App\Enums\PricingStrategy;
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
        Schema::create('pricing_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Pricing strategy
            $table->enum('pricing_strategy', array_column(PricingStrategy::cases(), 'value'))
                ->default('STANDARD');

            // Default settings for this group
            $table->decimal('default_discount_percent', 5, 2)->nullable();
            $table->decimal('default_markup_percent', 5, 2)->nullable();
            $table->boolean('allow_manual_override')->default(true);
            $table->boolean('enforce_minimum_margin')->default(false);
            $table->decimal('minimum_margin_percent', 5, 2)->nullable();

            // Currency
            $table->string('currency_code', 3)->default('USD');

            // Date range for group validity
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Link to posting group (for reporting alignment)
            $table->foreignId('general_business_posting_group_id')
                ->nullable()
                ->constrained('general_business_posting_groups')
                ->comment('Align pricing with P&L segments');

            $table->boolean('blocked')->default(false);
            $table->timestamps();

            $table->index(['code', 'blocked']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_groups');
    }
};
