<?php

namespace Database\Seeders;

use App\Enums\DepreciationMethod;
use App\Enums\FAPostingType;
use App\Enums\FAStatus;
use App\Enums\FixedAssetType;
use App\Models\DepreciationBook;
use App\Models\FAClass;
use App\Models\FALedgerEntry;
use App\Models\FAPostingGroup;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FixedAssetSampleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userId = User::query()->orderBy('id')->value('id');

        if ($userId === null) {
            return;
        }

        $vendorId = Vendor::query()->orderBy('id')->value('id');
        $locations = Location::query()->active()->orderBy('id')->get(['id']);
        $depreciationBookId = DepreciationBook::query()->where('code', 'CORP')->value('id');
        $primaryLocationId = $locations->get(0)?->id;
        $secondaryLocationId = $locations->get(1)?->id ?? $primaryLocationId;

        if ($depreciationBookId === null || $primaryLocationId === null) {
            return;
        }

        $assets = [
            [
                'fa_no' => 'FA-DEMO-0001',
                'description' => 'Sample Production Mixer',
                'description_2' => 'Demo fixed asset for report review',
                'search_description' => 'Mixer demo asset',
                'fa_class_code' => 'MACHINERY',
                'fa_posting_group_code' => 'MACHINERY',
                'location_id' => $primaryLocationId,
                'acquisition_date' => '2026-01-15',
                'depreciation_starting_date' => '2026-01-15',
                'depreciation_ending_date' => '2030-12-31',
                'acquisition_cost' => 2500000,
                'acquisition_invoice_no' => 'FA-INV-2026-0001',
                'depreciation_rate' => 20,
                'useful_life_years' => 5,
                'useful_life_months' => 60,
                'book_value' => 2500000,
                'accumulated_depreciation' => 83333.3334,
                'entries' => [
                    [
                        'entry_no' => 1,
                        'fa_posting_type' => FAPostingType::ACQUISITION,
                        'posting_date' => '2026-01-15',
                        'amount' => 2500000,
                        'amount_lcy' => 2500000,
                        'depreciation_amount' => 0,
                        'accumulated_depreciation' => 0,
                        'book_value_after' => 2500000,
                        'description' => 'Acquisition: Sample Production Mixer',
                    ],
                    [
                        'entry_no' => 2,
                        'fa_posting_type' => FAPostingType::DEPRECIATION,
                        'posting_date' => '2026-02-28',
                        'amount' => -41666.6667,
                        'amount_lcy' => -41666.6667,
                        'depreciation_amount' => 41666.6667,
                        'accumulated_depreciation' => 41666.6667,
                        'book_value_after' => 2458333.3333,
                        'description' => 'Monthly depreciation - February 2026',
                    ],
                    [
                        'entry_no' => 3,
                        'fa_posting_type' => FAPostingType::DEPRECIATION,
                        'posting_date' => '2026-03-31',
                        'amount' => -41666.6667,
                        'amount_lcy' => -41666.6667,
                        'depreciation_amount' => 41666.6667,
                        'accumulated_depreciation' => 83333.3334,
                        'book_value_after' => 2416666.6666,
                        'description' => 'Monthly depreciation - March 2026',
                    ],
                ],
            ],
            [
                'fa_no' => 'FA-DEMO-0002',
                'description' => 'Sample Delivery Van',
                'description_2' => 'Second demo asset for filter review',
                'search_description' => 'Van demo asset',
                'fa_class_code' => 'VEHICLES',
                'fa_posting_group_code' => 'VEHICLES',
                'location_id' => $secondaryLocationId,
                'acquisition_date' => '2026-02-10',
                'depreciation_starting_date' => '2026-02-10',
                'depreciation_ending_date' => '2029-01-31',
                'acquisition_cost' => 1800000,
                'acquisition_invoice_no' => 'FA-INV-2026-0002',
                'depreciation_rate' => 25,
                'useful_life_years' => 3,
                'useful_life_months' => 36,
                'book_value' => 1800000,
                'accumulated_depreciation' => 100000,
                'entries' => [
                    [
                        'entry_no' => 1,
                        'fa_posting_type' => FAPostingType::ACQUISITION,
                        'posting_date' => '2026-02-10',
                        'amount' => 1800000,
                        'amount_lcy' => 1800000,
                        'depreciation_amount' => 0,
                        'accumulated_depreciation' => 0,
                        'book_value_after' => 1800000,
                        'description' => 'Acquisition: Sample Delivery Van',
                    ],
                    [
                        'entry_no' => 2,
                        'fa_posting_type' => FAPostingType::DEPRECIATION,
                        'posting_date' => '2026-03-31',
                        'amount' => -50000,
                        'amount_lcy' => -50000,
                        'depreciation_amount' => 50000,
                        'accumulated_depreciation' => 50000,
                        'book_value_after' => 1750000,
                        'description' => 'Monthly depreciation - March 2026',
                    ],
                    [
                        'entry_no' => 3,
                        'fa_posting_type' => FAPostingType::DEPRECIATION,
                        'posting_date' => '2026-04-30',
                        'amount' => -50000,
                        'amount_lcy' => -50000,
                        'depreciation_amount' => 50000,
                        'accumulated_depreciation' => 100000,
                        'book_value_after' => 1700000,
                        'description' => 'Monthly depreciation - April 2026',
                    ],
                ],
            ],
            [
                'fa_no' => 'FA-DEMO-0003',
                'description' => 'Sample Retired Packing Machine',
                'description_2' => 'Disposed demo asset for edge-case review',
                'search_description' => 'Disposed packing machine',
                'fa_class_code' => 'MACHINERY',
                'fa_posting_group_code' => 'MACHINERY',
                'location_id' => $secondaryLocationId,
                'acquisition_date' => '2025-06-01',
                'depreciation_starting_date' => '2025-06-01',
                'depreciation_ending_date' => '2028-05-31',
                'acquisition_cost' => 900000,
                'acquisition_invoice_no' => 'FA-INV-2025-0003',
                'depreciation_rate' => 33.3333,
                'useful_life_years' => 3,
                'useful_life_months' => 36,
                'book_value' => 900000,
                'accumulated_depreciation' => 300000,
                'status' => FAStatus::DISPOSED,
                'disposal_date' => '2026-05-20',
                'disposal_proceeds' => 580000,
                'disposal_cost' => 0,
                'entries' => [
                    [
                        'entry_no' => 1,
                        'fa_posting_type' => FAPostingType::ACQUISITION,
                        'posting_date' => '2025-06-01',
                        'amount' => 900000,
                        'amount_lcy' => 900000,
                        'depreciation_amount' => 0,
                        'accumulated_depreciation' => 0,
                        'book_value_after' => 900000,
                        'description' => 'Acquisition: Sample Retired Packing Machine',
                    ],
                    [
                        'entry_no' => 2,
                        'fa_posting_type' => FAPostingType::DEPRECIATION,
                        'posting_date' => '2026-04-30',
                        'amount' => -300000,
                        'amount_lcy' => -300000,
                        'depreciation_amount' => 300000,
                        'accumulated_depreciation' => 300000,
                        'book_value_after' => 600000,
                        'description' => 'Depreciation up to disposal review',
                    ],
                    [
                        'entry_no' => 3,
                        'fa_posting_type' => FAPostingType::DISPOSAL,
                        'posting_date' => '2026-05-20',
                        'amount' => -600000,
                        'amount_lcy' => -600000,
                        'depreciation_amount' => 0,
                        'accumulated_depreciation' => 300000,
                        'book_value_after' => 0,
                        'description' => 'Disposal: Sample Retired Packing Machine',
                    ],
                ],
            ],
        ];

        foreach ($assets as $assetData) {
            $postingGroupId = FAPostingGroup::query()->where('code', $assetData['fa_posting_group_code'])->value('id');
            $faClassId = FAClass::query()->where('code', $assetData['fa_class_code'])->value('id');

            if ($postingGroupId === null || $faClassId === null) {
                continue;
            }

            $asset = FixedAsset::updateOrCreate(
                ['fa_no' => $assetData['fa_no']],
                [
                    'description' => $assetData['description'],
                    'description_2' => $assetData['description_2'],
                    'search_description' => $assetData['search_description'],
                    'fa_type' => FixedAssetType::TANGIBLE,
                    'fa_class_id' => $faClassId,
                    'fa_posting_group_id' => $postingGroupId,
                    'depreciation_book_id' => $depreciationBookId,
                    'vendor_id' => $vendorId,
                    'main_vendor_id' => $vendorId,
                    'location_id' => $assetData['location_id'],
                    'acquisition_date' => $assetData['acquisition_date'],
                    'depreciation_starting_date' => $assetData['depreciation_starting_date'],
                    'depreciation_ending_date' => $assetData['depreciation_ending_date'],
                    'acquisition_cost' => $assetData['acquisition_cost'],
                    'acquisition_vendor_id' => $vendorId,
                    'acquisition_invoice_no' => $assetData['acquisition_invoice_no'],
                    'depreciation_method' => DepreciationMethod::STRAIGHT_LINE,
                    'depreciation_rate' => $assetData['depreciation_rate'],
                    'useful_life_years' => $assetData['useful_life_years'],
                    'useful_life_months' => $assetData['useful_life_months'],
                    'salvage_value' => 0,
                    'units_produced_to_date' => 0,
                    'book_value' => $assetData['book_value'],
                    'accumulated_depreciation' => $assetData['accumulated_depreciation'],
                    'status' => $assetData['status'] ?? FAStatus::ACTIVE,
                    'blocked' => false,
                    'disposal_date' => $assetData['disposal_date'] ?? null,
                    'disposal_proceeds' => $assetData['disposal_proceeds'] ?? null,
                    'disposal_cost' => $assetData['disposal_cost'] ?? null,
                    'created_by' => $userId,
                    'modified_by' => $userId,
                ]
            );

            foreach ($assetData['entries'] as $entry) {
                FALedgerEntry::query()->updateOrInsert(
                    [
                        'fixed_asset_id' => $asset->id,
                        'depreciation_book_id' => $depreciationBookId,
                        'entry_no' => $entry['entry_no'],
                    ],
                    [
                        'fa_posting_type' => $entry['fa_posting_type'],
                        'document_type' => 'fixed_asset',
                        'document_no' => $assetData['fa_no'],
                        'document_line_no' => $entry['entry_no'],
                        'posting_date' => $entry['posting_date'],
                        'amount' => $entry['amount'],
                        'amount_lcy' => $entry['amount_lcy'],
                        'depreciation_amount' => $entry['depreciation_amount'],
                        'accumulated_depreciation' => $entry['accumulated_depreciation'],
                        'book_value_after' => $entry['book_value_after'],
                        'description' => $entry['description'],
                        'source_code' => 'FASEED',
                        'created_by' => $userId,
                        'entry_timestamp' => Carbon::parse($entry['posting_date'])->setTime(12, 0),
                        'reversed' => false,
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
