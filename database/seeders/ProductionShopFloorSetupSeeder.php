<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProductionDowntimeCategory;
use App\Enums\ProductionScrapPostingTreatment;
use App\Enums\ProductionScrapStage;
use App\Models\Manufacturing\ProductionDowntimeReason;
use App\Models\Manufacturing\ProductionScrapReason;
use Illuminate\Database\Seeder;

class ProductionShopFloorSetupSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->scrapReasons() as $reason) {
            ProductionScrapReason::query()->updateOrCreate(
                ['code' => $reason['code']],
                $reason,
            );
        }

        foreach ($this->downtimeReasons() as $reason) {
            ProductionDowntimeReason::query()->updateOrCreate(
                ['code' => $reason['code']],
                $reason,
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scrapReasons(): array
    {
        return [
            [
                'code' => 'SETUP-WASTE',
                'name' => 'Setup Waste',
                'description' => 'Material lost during equipment setup or changeover.',
                'stage' => ProductionScrapStage::Setup,
                'default_posting_treatment' => ProductionScrapPostingTreatment::ProductionVariance,
                'requires_approval' => false,
                'requires_quality_review' => false,
                'recoverable' => false,
                'reworkable' => false,
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'QC-REJECT',
                'name' => 'Quality Reject',
                'description' => 'Output rejected by quality inspection.',
                'stage' => ProductionScrapStage::Quality,
                'default_posting_treatment' => ProductionScrapPostingTreatment::ReducedOutput,
                'requires_approval' => true,
                'requires_quality_review' => true,
                'recoverable' => false,
                'reworkable' => true,
                'is_active' => true,
                'sort_order' => 20,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function downtimeReasons(): array
    {
        return [
            [
                'code' => 'CHANGEOVER',
                'name' => 'Changeover',
                'description' => 'Planned downtime for product or tooling changeover.',
                'category' => ProductionDowntimeCategory::Changeover,
                'requires_approval' => false,
                'blocks_completion' => false,
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'MACHINE-FAULT',
                'name' => 'Machine Fault',
                'description' => 'Unplanned downtime caused by machine fault.',
                'category' => ProductionDowntimeCategory::Maintenance,
                'requires_approval' => true,
                'blocks_completion' => false,
                'is_active' => true,
                'sort_order' => 20,
            ],
        ];
    }
}
