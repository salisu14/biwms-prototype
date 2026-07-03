<?php

declare(strict_types=1);

namespace App\Services\FixedAsset;

use App\Enums\FAPostingType;
use App\Enums\FAStatus;
use App\Models\FixedAsset;
use App\Services\NumberSeriesService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AcquisitionService
{
    public function __construct(
        private readonly NumberSeriesService $numberSeriesService,
        private readonly FAPostingService $postingService
    ) {}

    public function acquire(array $data): FixedAsset
    {
        return DB::transaction(function () use ($data) {
            // Create or update asset
            $asset = $this->createAsset($data);

            // Post acquisition
            $this->postAcquisition($asset, $data);

            // Update asset status
            $asset->update([
                'status' => FAStatus::ACTIVE,
                'book_value' => $data['acquisition_cost'],
            ]);

            return $asset;
        });
    }

    public function capitalizeConstruction(
        int $assetId,
        float $additionalCost,
        string $description
    ): void {
        $asset = FixedAsset::findOrFail($assetId);

        if ($asset->status !== FAStatus::UNDER_CONSTRUCTION) {
            throw new \RuntimeException('Asset must be under construction to capitalize');
        }

        DB::transaction(function () use ($asset, $additionalCost, $description) {
            // Post to CWIP account
            $this->postingService->postEntry(
                asset: $asset,
                postingType: FAPostingType::ACQUISITION,
                amount: $additionalCost,
                description: "CWIP: {$description}",
                documentNo: $this->numberSeriesService->getNextNo('FA-ACQ')
            );

            $asset->forceFill([
                'acquisition_cost' => (float) $asset->acquisition_cost + $additionalCost,
                'book_value' => (float) $asset->book_value + $additionalCost,
            ])->save();
        });
    }

    public function completeConstruction(int $assetId, \DateTime $completionDate): void
    {
        $asset = FixedAsset::findOrFail($assetId);

        DB::transaction(function () use ($asset, $completionDate) {
            // Transfer from CWIP to Fixed Asset
            $this->postingService->postTransfer(
                asset: $asset,
                fromAccount: $asset->posting_group->capitalization_account_id,
                toAccount: $asset->posting_group->acquisition_cost_account_id,
                amount: $asset->book_value,
                description: 'CWIP to Fixed Asset completion'
            );

            $asset->update([
                'status' => FAStatus::ACTIVE,
                'depreciation_starting_date' => $completionDate,
                'acquisition_date' => $completionDate,
            ]);
        });
    }

    private function createAsset(array $data): FixedAsset
    {
        return FixedAsset::create([
            'fa_no' => $data['fa_no'] ?? $this->numberSeriesService->getNextNo('FA'),
            'description' => $data['description'],
            'fa_type' => $data['fa_type'],
            'fa_posting_group_id' => $data['fa_posting_group_id'],
            'depreciation_book_id' => $data['depreciation_book_id'],
            'acquisition_date' => $data['acquisition_date'],
            'acquisition_cost' => 0,
            'book_value' => 0,
            'depreciation_starting_date' => $data['depreciation_starting_date'] ?? $data['acquisition_date'],
            'depreciation_method' => $data['depreciation_method'],
            'useful_life_years' => $data['useful_life_years'],
            'salvage_value' => $data['salvage_value'] ?? 0,
            'status' => $data['status'] ?? FAStatus::NEW,
            'created_by' => Auth::id(),
        ]);
    }

    private function postAcquisition(FixedAsset $asset, array $data): void
    {
        $documentNo = $data['document_no'] ?? $this->numberSeriesService->getNextNo('FA-ACQ');

        $this->postingService->postEntry(
            asset: $asset,
            postingType: FAPostingType::ACQUISITION,
            amount: (float) $data['acquisition_cost'],
            description: "Acquisition: {$asset->description}",
            documentNo: $documentNo,
            postingDate: Carbon::parse($data['acquisition_date'])->toDateTime(),
            additionalData: [
                'document_type' => $data['document_type'] ?? 'Purchase Invoice',
                'book_value_after' => (float) $data['acquisition_cost'],
                'offset_account_id' => $data['offset_account_id'] ?? null,
            ]
        );
    }
}
