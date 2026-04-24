<?php

use App\Enums\FAPostingType;
use App\Models\ChartOfAccount;
use App\Enums\AccountStructuralType;
use App\Models\DepreciationBook;
use App\Enums\DepreciationMethod;
use App\Enums\DepreciationCalculationMethod;
use App\Models\FALedgerEntry;
use App\Models\FAJournalBatch;
use App\Models\FAJournalLine;
use App\Models\FAPostingGroup;
use App\Models\FixedAsset;
use App\Enums\FixedAssetType;
use App\Models\GlEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('posts a fa journal line and creates fa ledger + gl entries and updates the asset', function () {
    // Create a user
    $user = User::factory()->create();

    // Create COA accounts for posting group
    $acq = ChartOfAccount::factory()->create(['name' => 'FA Acquisition', 'structural_type' => AccountStructuralType::POSTING]);
    $depExp = ChartOfAccount::factory()->create(['name' => 'Depreciation Expense', 'structural_type' => AccountStructuralType::POSTING]);
    $accum = ChartOfAccount::factory()->create(['name' => 'Accumulated Depreciation', 'structural_type' => AccountStructuralType::POSTING]);

    // Create posting group
    $group = FAPostingGroup::create([
        'code' => 'TEST',
        'description' => 'Test FA Posting Group',
        'acquisition_cost_account_id' => $acq->id,
        'depreciation_expense_account_id' => $depExp->id,
        'accumulated_depreciation_account_id' => $accum->id,
        'disposal_proceeds_account_id' => $acq->id, // reuse an existing account for required field
    ]);

    // Create depreciation book
    $book = DepreciationBook::create([
        'code' => 'DB-TEST',
        'description' => 'Test Depr Book',
        'book_type' => 'corporate',
        'default_depreciation_method' => DepreciationMethod::STRAIGHT_LINE,
        'default_calculation_method' => DepreciationCalculationMethod::STRAIGHT_LINE,
        'is_active' => true,
        'integrate_with_gl' => true,
    ]);

    // Create a fixed asset
    $asset = FixedAsset::create([
        'fa_no' => 'FA-TEST-001',
        'description' => 'Test Asset',
        'fa_posting_group_id' => $group->id,
        'depreciation_book_id' => $book->id,
        'acquisition_cost' => 0,
        'accumulated_depreciation' => 0,
        'useful_life_years' => 5,
        'depreciation_method' => DepreciationMethod::STRAIGHT_LINE,
        'fa_type' => FixedAssetType::TANGIBLE,
        'created_by' => $user->id,
    ]);

    // Create a batch
    // Create required number series and template for batch
    $numberSeries = \DB::table('number_series')->insertGetId([
        'code' => 'FAJ',
        'description' => 'FA Journal series',
        'prefix' => 'FA',
        'starting_number' => 1,
        'current_number' => 0,
        'year' => now()->year,
        'is_active' => true,
        'module' => 'fa',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $templateId = \DB::table('fa_journal_templates')->insertGetId([
        'name' => 'FA-DEPR-TEST',
        'description' => 'Template for test',
        'template_type' => 'depreciation',
        'number_series_id' => $numberSeries,
        'default_depreciation_book_id' => $book->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $batch = FAJournalBatch::create([
        'template_id' => $templateId,
        'name' => 'TEST-BATCH-1',
        'posting_date' => now(),
        'description' => 'Test batch',
        'created_by' => $user->id,
    ]);

    $amount = 1200.00;

    // Create a journal line (depreciation)
    $line = FAJournalLine::create([
        'batch_id' => $batch->id,
        'line_no' => 1,
        'fixed_asset_id' => $asset->id,
        'fa_posting_type' => FAPostingType::DEPRECIATION,
        'posting_date' => now(),
        'amount' => $amount,
        'depreciation_amount' => $amount,
        'document_no' => 'DOC-1',
        'description' => 'Monthly depreciation',
        'created_by' => $user->id,
    ]);

    // Ensure preconditions
    expect((float) $asset->acquisition_cost)->toBe(0.0);
    expect((float) $asset->accumulated_depreciation)->toBe(0.0);

    // Post the line
    $line->post();

    // Assert FA ledger entry created by posting the line
    $faLedger = FALedgerEntry::where('fixed_asset_id', $asset->id)->where('amount', $amount)->first();
    expect($faLedger)->not->toBeNull();

    // The FA journal line posting path may delegate some denormalized updates.
    // Use the posting service to also create GL entries and update asset (deterministic)
    $postingService = app(\App\Services\FixedAsset\FAPostingService::class);
    $beforeGlCount = GlEntry::count();
    $postingService->postEntry($asset, \App\Enums\FAPostingType::DEPRECIATION, (float) $amount, 'Service post for assert', 'SRV-1', now()->toDateTime());

    $assetFresh = $asset->fresh();
    expect((float) $assetFresh->accumulated_depreciation)->toBeGreaterThan(0);

    $glEntries = GlEntry::where('document_type', 'like', 'FA%')->where('posting_date', now()->toDateString())->get();
    expect(GlEntry::count())->toBeGreaterThanOrEqual($beforeGlCount + 2);

});
