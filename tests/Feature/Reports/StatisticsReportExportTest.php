<?php

use App\Enums\AccountStructuralType;
use App\Models\Business;
use App\Models\ChartOfAccount;
use App\Models\CompanyInformation;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GlEntry;
use App\Models\User;
use App\Services\Finance\StatisticsReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exports sales statistics as csv and print html', function (): void {
    ensureStatisticsCompanyProfile();
    $user = User::factory()->create();
    $postingGroup = GeneralBusinessPostingGroup::factory()->create([
        'code' => 'DOM',
        'description' => 'Domestic',
    ]);
    $accountPostingGroup = GeneralBusinessPostingGroup::factory()->create([
        'code' => 'ACC',
        'description' => 'Account Group',
    ]);

    $account = ChartOfAccount::factory()->create([
        'structural_type' => AccountStructuralType::POSTING,
        'gen_bus_posting_group_id' => $accountPostingGroup->id,
    ]);

    GlEntry::query()->create([
        'business_id' => Business::query()->firstOrFail()->id,
        'entry_number' => 1001,
        'transaction_number' => 5001,
        'chart_of_account_id' => $account->id,
        'general_business_posting_group_id' => $postingGroup->id,
        'debit_amount' => 0,
        'credit_amount' => 1250,
        'amount' => -1250,
        'document_type' => 'SALES_INVOICE',
        'document_number' => 'SI-001',
        'document_date' => '2026-06-01',
        'posting_date' => '2026-06-01',
        'description' => 'Sales statistics test',
    ]);

    $csvResponse = $this
        ->actingAs($user)
        ->get(route('reports.sales-statistics.export', [
            'format' => 'csv',
            'business_id' => Business::query()->firstOrFail()->id,
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
            'gen_bus_posting_group_id' => $postingGroup->id,
        ]))
        ->assertOk();

    expect($csvResponse->streamedContent())
        ->toContain('Sales Statistics')
        ->toContain('DOM')
        ->toContain('1250.00')
        ->not->toContain('ACC');

    $this
        ->actingAs($user)
        ->get(route('reports.sales-statistics.export', [
            'format' => 'print',
            'business_id' => Business::query()->firstOrFail()->id,
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
            'gen_bus_posting_group_id' => $postingGroup->id,
        ]))
        ->assertOk()
        ->assertSee('Sales Statistics')
        ->assertSee('Domestic')
        ->assertDontSee('Account Group')
        ->assertSee('1,250.00');
});

it('uses stored LCY snapshots for mixed-currency sales statistics', function (): void {
    $business = Business::query()->create([
        'code' => 'FCY-STATS',
        'name' => 'FCY Statistics Business',
        'is_active' => true,
    ]);
    $account = ChartOfAccount::factory()->create([
        'structural_type' => AccountStructuralType::POSTING,
    ]);

    GlEntry::query()->create([
        'business_id' => $business->id,
        'entry_number' => 1010,
        'transaction_number' => 5010,
        'chart_of_account_id' => $account->id,
        'debit_amount' => 0,
        'debit_amount_lcy' => 0,
        'credit_amount' => 100,
        'credit_amount_lcy' => 150000,
        'amount' => -100,
        'amount_lcy' => -150000,
        'document_type' => 'SALES_INVOICE',
        'document_number' => 'FCY-ST-001',
        'document_date' => '2026-06-01',
        'posting_date' => '2026-06-01',
        'description' => 'FCY statistics test',
    ]);

    $report = app(StatisticsReportService::class)->sales([
        'business_id' => $business->id,
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
    ]);

    expect($report['summary']['total_amount'])->toBe(150000.0);
});

it('exports purchase statistics as csv and print html', function (): void {
    ensureStatisticsCompanyProfile();
    $user = User::factory()->create();
    $postingGroup = GeneralBusinessPostingGroup::factory()->create([
        'code' => 'LOCAL',
        'description' => 'Local Procurement',
    ]);
    $accountPostingGroup = GeneralBusinessPostingGroup::factory()->create([
        'code' => 'ACC',
        'description' => 'Account Group',
    ]);

    $account = ChartOfAccount::factory()->create([
        'structural_type' => AccountStructuralType::POSTING,
        'gen_bus_posting_group_id' => $accountPostingGroup->id,
    ]);

    GlEntry::query()->create([
        'business_id' => Business::query()->firstOrFail()->id,
        'entry_number' => 1002,
        'transaction_number' => 5002,
        'chart_of_account_id' => $account->id,
        'general_business_posting_group_id' => $postingGroup->id,
        'debit_amount' => 750,
        'credit_amount' => 0,
        'amount' => 750,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-001',
        'document_date' => '2026-06-02',
        'posting_date' => '2026-06-02',
        'description' => 'Purchase statistics test',
    ]);

    $csvResponse = $this
        ->actingAs($user)
        ->get(route('reports.purchase-statistics.export', [
            'format' => 'csv',
            'business_id' => Business::query()->firstOrFail()->id,
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
            'gen_bus_posting_group_id' => $postingGroup->id,
        ]))
        ->assertOk();

    expect($csvResponse->streamedContent())
        ->toContain('Purchase Statistics')
        ->toContain('LOCAL')
        ->toContain('750.00')
        ->not->toContain('ACC');

    $this
        ->actingAs($user)
        ->get(route('reports.purchase-statistics.export', [
            'format' => 'print',
            'business_id' => Business::query()->firstOrFail()->id,
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
            'gen_bus_posting_group_id' => $postingGroup->id,
        ]))
        ->assertOk()
        ->assertSee('Purchase Statistics')
        ->assertSee('Local Procurement')
        ->assertDontSee('Account Group')
        ->assertSee('750.00');
});

function ensureStatisticsCompanyProfile(): void
{
    $businesses = Business::query()->get();
    if ($businesses->isEmpty()) {
        $businesses = collect([Business::query()->create([
            'code' => 'REPORT-CO',
            'name' => 'Report Business',
            'is_active' => true,
        ])]);
    }
    session(['active_business_id' => $businesses->first()?->id]);

    foreach ($businesses as $business) {
        CompanyInformation::query()->firstOrCreate(['business_id' => $business->id], [
            'company_name' => $business->name,
            'country_code' => 'NGA',
        ]);
    }

    if (! CompanyInformation::query()->whereNull('business_id')->exists()) {
        CompanyInformation::query()->create([
            'company_name' => 'Statistics Test Company',
            'country_code' => 'NGA',
        ]);
    }
}
