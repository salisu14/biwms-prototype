<?php

use App\Enums\AccountStructuralType;
use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GlEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exports sales statistics as csv and print html', function (): void {
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

it('exports purchase statistics as csv and print html', function (): void {
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
