<?php

declare(strict_types=1);

use App\Enums\ProductionCampaignStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('status', 40)->default(ProductionCampaignStatus::Draft->value);
            $table->string('grouping_key')->nullable();
            $table->string('grouping_rule', 60)->default('planner_selected');
            $table->timestamp('planned_start_at')->nullable();
            $table->timestamp('planned_end_at')->nullable();
            $table->unsignedInteger('sequence')->default(10000);
            $table->decimal('setup_reduction_percent', 8, 4)->default(0);
            $table->text('changeover_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status'], 'production_campaigns_business_status_idx');
            $table->index(['work_center_id', 'planned_start_at', 'planned_end_at'], 'production_campaigns_wc_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_campaigns');
    }
};
