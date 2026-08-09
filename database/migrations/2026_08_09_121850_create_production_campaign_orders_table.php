<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_campaign_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_campaign_id')->constrained('production_campaigns')->cascadeOnDelete();
            $table->foreignId('production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->unsignedInteger('sequence')->default(10000);
            $table->decimal('planned_quantity_base', 18, 8)->default(0);
            $table->string('setup_class')->nullable();
            $table->string('changeover_class')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['production_campaign_id', 'production_order_id'], 'production_campaign_orders_unique');
            $table->index(['production_order_id'], 'production_campaign_orders_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_campaign_orders');
    }
};
