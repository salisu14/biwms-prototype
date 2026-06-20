<?php

declare(strict_types=1);

namespace App\Services\FixedAsset;

use App\Enums\FAPostingType;
use App\Models\FAJournalBatch;
use App\Models\FAJournalLine;
use App\Models\FAJournalTemplate;
use App\Models\FixedAsset;
use Carbon\Carbon;

class DepreciationBatchService
{
    public function __construct(
        private readonly DepreciationCalculationService $calculationService,
        private readonly FAPostingService $postingService
    ) {}

    public function createDepreciationBatch(
        \DateTime $postingDate,
        ?array $assetFilter = null,
        ?int $templateId = null
    ): FAJournalBatch {
        $batch = FAJournalBatch::create([
            'template_id' => $templateId ?? $this->getDefaultDepreciationTemplate(),
            'name' => 'DEPR-'.$postingDate->format('Y-m'),
            'description' => 'Monthly depreciation run '.$postingDate->format('F Y'),
            'posting_date' => $postingDate,
            'calculate_depreciation' => true,
            'status' => 'open',
        ]);

        // Get assets to depreciate
        $assets = FixedAsset::where('status', 'active')
            ->where('blocked', false)
            ->when($assetFilter, fn ($q) => $q->whereIn('id', $assetFilter))
            ->get();

        $lineNo = 10000;

        foreach ($assets as $asset) {
            $amount = $this->calculationService->calculate(
                $asset,
                Carbon::parse($postingDate)->startOfMonth()->toDateTime(),
                $postingDate
            );

            if ($amount <= 0) {
                continue;
            }

            FAJournalLine::create([
                'batch_id' => $batch->id,
                'line_no' => $lineNo,
                'fixed_asset_id' => $asset->id,
                'fa_no' => $asset->fa_no,
                'posting_date' => $postingDate,
                'fa_posting_type' => FAPostingType::DEPRECIATION,
                'description' => "Depreciation {$asset->fa_no} for ".$postingDate->format('F Y'),
                'calculated_amount' => $amount,
                'amount' => $amount,
                'calculate_depreciation' => false, // Already calculated
                'line_status' => 'calculated',
                'created_by' => auth()->id(),
            ]);

            $lineNo += 10000;
        }

        return $batch;
    }

    public function postBatch(FAJournalBatch $batch): void
    {
        if ($batch->status !== 'released') {
            throw new \RuntimeException('Batch must be released before posting');
        }

        foreach ($batch->lines as $line) {
            if ($line->line_status !== 'posted') {
                $this->postingService->postEntry(
                    asset: $line->fixedAsset,
                    postingType: $line->fa_posting_type,
                    amount: $line->amount,
                    description: $line->description,
                    documentNo: $batch->name,
                    postingDate: $line->posting_date
                );

                $line->update(['line_status' => 'posted']);
            }
        }

        $batch->update(['status' => 'posted']);
    }

    private function getDefaultDepreciationTemplate(): int
    {
        return FAJournalTemplate::where('template_type', 'depreciation')->first()?->id ?? 1;
    }
}
