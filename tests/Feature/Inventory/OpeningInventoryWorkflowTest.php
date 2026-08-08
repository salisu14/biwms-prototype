<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\ItemType;
use App\Filament\Resources\OpeningInventories\OpeningInventoryResource;
use App\Filament\Resources\OpeningInventories\Pages\CreateOpeningInventory;
use App\Filament\Resources\OpeningInventories\Pages\EditOpeningInventory;
use App\Filament\Resources\OpeningInventories\Pages\ListOpeningInventories;
use App\Filament\Resources\OpeningInventories\Pages\ViewOpeningInventory;
use App\Models\AccountingPeriod;
use App\Models\Business;
use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralLedgerSetup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\ItemUomAssignment;
use App\Models\Location;
use App\Models\OpeningInventory;
use App\Models\OpeningInventoryLine;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\ValueEntry;
use App\Policies\OpeningInventoryPolicy;
use App\Services\Inventory\OpeningInventoryService;
use App\Services\Inventory\ValueEntryService;
use Database\Seeders\PermissionsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    openingInventoryWorkflowAccountingSetup();
});

it('renders opening inventory Filament list create edit and view pages', function (): void {
    $user = openingInventoryWorkflowSuperAdmin();
    $document = openingInventoryWorkflowDocument('OPEN-FILAMENT-001');

    $this->actingAs($user)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get(OpeningInventoryResource::getUrl('index'))
        ->assertSuccessful();

    $this->actingAs($user)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get(OpeningInventoryResource::getUrl('create'))
        ->assertSuccessful();

    $this->actingAs($user)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get(OpeningInventoryResource::getUrl('edit', ['record' => $document]))
        ->assertSuccessful();

    $this->actingAs($user)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get(OpeningInventoryResource::getUrl('view', ['record' => $document]))
        ->assertSuccessful()
        ->assertSee('OPEN-FILAMENT-001');
});

it('creates a draft opening inventory through the Filament create page and redirects to the record', function (): void {
    $user = openingInventoryWorkflowSuperAdmin();
    $fixture = openingInventoryWorkflowFixture();

    $component = Livewire::actingAs($user)
        ->test(CreateOpeningInventory::class)
        ->fillForm(openingInventoryWorkflowFilamentPayload($fixture, 'OPEN-FILAMENT-CREATE-001'))
        ->call('create')
        ->assertHasNoFormErrors();

    $document = OpeningInventory::query()
        ->where('document_number', 'OPEN-FILAMENT-CREATE-001')
        ->firstOrFail();

    $component->assertRedirect(OpeningInventoryResource::getUrl('view', ['record' => $document]));

    expect($document->business_id)->toBe($fixture['business']->id)
        ->and($document->status)->toBe(OpeningInventory::STATUS_DRAFT)
        ->and($document->lines()->count())->toBe(2)
        ->and($document->lines()->pluck('line_number')->all())->toBe([10000, 20000]);
});

it('edits an existing draft through Filament while preserving persisted line identities', function (): void {
    $user = openingInventoryWorkflowSuperAdmin();
    $fixture = openingInventoryWorkflowFixture();
    $document = app(OpeningInventoryService::class)->createDraft(
        documentNumber: 'OPEN-FILAMENT-EDIT-001',
        source: 'MANUAL',
        postingDate: '2026-08-01',
        lines: [
            openingInventoryWorkflowLine($fixture, quantity: '2.00000000'),
            openingInventoryWorkflowLine($fixture, quantity: '3.00000000'),
        ],
        businessId: $fixture['business']->id,
        createdBy: $user->id,
    );
    $lineIds = $document->lines()->orderBy('line_number')->pluck('id')->all();

    Livewire::actingAs($user)
        ->test(EditOpeningInventory::class, ['record' => $document->getRouteKey()])
        ->fillForm(openingInventoryWorkflowFilamentPayload($fixture, 'OPEN-FILAMENT-EDIT-001', [
            [
                'id' => $lineIds[0],
                ...openingInventoryWorkflowLine($fixture, quantity: '5.00000000'),
            ],
            [
                'id' => $lineIds[1],
                ...openingInventoryWorkflowLine($fixture, quantity: '7.00000000'),
            ],
        ]))
        ->call('save')
        ->assertHasNoFormErrors();

    expect($document->fresh()->lines()->orderBy('line_number')->pluck('id')->all())->toBe($lineIds)
        ->and($document->fresh()->lines()->orderBy('line_number')->pluck('quantity')->all())->toBe(['5.00000000', '7.00000000']);
});

it('creates draft opening inventory and synchronizes draft lines through the service', function (): void {
    $fixture = openingInventoryWorkflowFixture();

    $document = app(OpeningInventoryService::class)->createDraft(
        documentNumber: 'OPEN-SYNC-001',
        source: 'MANUAL',
        postingDate: '2026-08-01',
        lines: [
            openingInventoryWorkflowLine($fixture, quantity: '2.00000000'),
            openingInventoryWorkflowLine($fixture, quantity: '3.00000000'),
        ],
        businessId: $fixture['business']->id,
        createdBy: $fixture['user']->id,
    );

    expect($document->lines)->toHaveCount(2);

    $firstLine = $document->lines->first();

    $updated = app(OpeningInventoryService::class)->updateDraft($document, [
        'document_number' => 'OPEN-SYNC-001',
        'business_id' => $fixture['business']->id,
        'posting_date' => '2026-08-01',
        'source' => 'MANUAL',
        'description' => 'Updated',
    ], [
        [
            'id' => $firstLine->id,
            ...openingInventoryWorkflowLine($fixture, quantity: '5.00000000'),
        ],
    ]);

    expect($updated->lines)->toHaveCount(1)
        ->and($updated->lines->first()->quantity)->toBe('5.00000000')
        ->and(OpeningInventoryLine::query()->where('opening_inventory_id', $document->id)->count())->toBe(1);
});

it('repeatedly saves, changes quantity, and reorders draft lines without duplicate line number collisions', function (): void {
    $fixture = openingInventoryWorkflowFixture();
    $document = app(OpeningInventoryService::class)->createDraft(
        documentNumber: 'OPEN-REORDER-001',
        source: 'MANUAL',
        postingDate: '2026-08-01',
        lines: [
            openingInventoryWorkflowLine($fixture, quantity: '1.00000000'),
            openingInventoryWorkflowLine($fixture, quantity: '2.00000000'),
            openingInventoryWorkflowLine($fixture, quantity: '3.00000000'),
        ],
        businessId: $fixture['business']->id,
        createdBy: $fixture['user']->id,
    );

    $originalLines = $document->lines()->orderBy('line_number')->get();

    app(OpeningInventoryService::class)->updateDraft($document, [
        'document_number' => 'OPEN-REORDER-001',
        'business_id' => $fixture['business']->id,
        'posting_date' => '2026-08-01',
        'source' => 'MANUAL',
    ], [
        ['id' => $originalLines[0]->id, ...openingInventoryWorkflowLine($fixture, quantity: '1.00000000')],
        ['id' => $originalLines[1]->id, ...openingInventoryWorkflowLine($fixture, quantity: '2.00000000')],
        ['id' => $originalLines[2]->id, ...openingInventoryWorkflowLine($fixture, quantity: '3.00000000')],
    ]);

    $updated = app(OpeningInventoryService::class)->updateDraft($document->fresh(), [
        'document_number' => 'OPEN-REORDER-001',
        'business_id' => $fixture['business']->id,
        'posting_date' => '2026-08-01',
        'source' => 'MANUAL',
    ], [
        ['id' => $originalLines[2]->id, ...openingInventoryWorkflowLine($fixture, quantity: '30.00000000')],
        openingInventoryWorkflowLine($fixture, quantity: '4.00000000'),
        ['id' => $originalLines[0]->id, ...openingInventoryWorkflowLine($fixture, quantity: '10.00000000')],
    ]);

    $lines = $updated->lines()->orderBy('line_number')->get();

    expect($lines)->toHaveCount(3)
        ->and($lines->pluck('line_number')->all())->toBe([10000, 20000, 30000])
        ->and($lines->pluck('id')->contains($originalLines[1]->id))->toBeFalse()
        ->and($lines->pluck('id')->contains($originalLines[2]->id))->toBeTrue()
        ->and($lines->pluck('id')->contains($originalLines[0]->id))->toBeTrue()
        ->and($lines->pluck('quantity')->all())->toBe(['30.00000000', '4.00000000', '10.00000000'])
        ->and(OpeningInventoryLine::query()
            ->select('line_number')
            ->where('opening_inventory_id', $document->id)
            ->groupBy('line_number')
            ->havingRaw('count(*) > 1')
            ->exists())->toBeFalse();
});

it('posts an existing production style draft with multiple persisted lines after idempotent line sync', function (): void {
    $fixture = openingInventoryWorkflowFixture();
    $document = OpeningInventory::query()->create([
        'business_id' => $fixture['business']->id,
        'document_number' => 'OPEN-PROD-DRAFT-01',
        'posting_date' => '2026-08-01',
        'status' => OpeningInventory::STATUS_DRAFT,
        'source' => 'MANUAL',
        'created_by' => $fixture['user']->id,
    ]);
    $first = OpeningInventoryLine::query()->create([
        'opening_inventory_id' => $document->id,
        'line_number' => 10000,
        ...openingInventoryWorkflowLine($fixture, quantity: '2.00000000'),
        'quantity_base' => '2.00000000',
        'amount' => '5.0000',
    ]);
    $second = OpeningInventoryLine::query()->create([
        'opening_inventory_id' => $document->id,
        'line_number' => 20000,
        ...openingInventoryWorkflowLine($fixture, quantity: '3.00000000'),
        'quantity_base' => '3.00000000',
        'amount' => '7.5000',
    ]);

    app(OpeningInventoryService::class)->updateDraft($document, [
        'document_number' => 'OPEN-PROD-DRAFT-01',
        'business_id' => $fixture['business']->id,
        'posting_date' => '2026-08-01',
        'source' => 'MANUAL',
    ], [
        ['id' => $second->id, ...openingInventoryWorkflowLine($fixture, quantity: '3.00000000')],
        ['id' => $first->id, ...openingInventoryWorkflowLine($fixture, quantity: '2.00000000')],
    ]);

    app(OpeningInventoryService::class)->post($document->fresh(), $fixture['user']->id);

    expect($document->fresh()->status)->toBe(OpeningInventory::STATUS_POSTED)
        ->and(ItemLedgerEntry::query()->where('source_type', OpeningInventory::class)->where('source_id', $document->id)->count())->toBe(2)
        ->and($document->fresh()->lines()->whereNotNull('item_ledger_entry_id')->count())->toBe(2);
});

it('posts opening inventory from view table and edit Filament flows', function (): void {
    $user = openingInventoryWorkflowSuperAdmin();
    $fixture = openingInventoryWorkflowFixture();
    $passwordConfirmation = ['security_password_confirmation' => 'password'];

    $viewDocument = openingInventoryWorkflowDocument('OPEN-VIEW-POST-01', $fixture);
    Livewire::actingAs($user)
        ->test(ViewOpeningInventory::class, ['record' => $viewDocument->getRouteKey()])
        ->callAction('post', data: $passwordConfirmation)
        ->assertHasNoActionErrors();

    $tableDocument = openingInventoryWorkflowDocument('OPEN-TABLE-POST-01', $fixture);
    Livewire::actingAs($user)
        ->test(ListOpeningInventories::class)
        ->callTableAction('post', $tableDocument, data: $passwordConfirmation)
        ->assertHasNoTableActionErrors();

    $editDocument = openingInventoryWorkflowDocument('OPEN-EDIT-POST-01', $fixture);
    Livewire::actingAs($user)
        ->test(EditOpeningInventory::class, ['record' => $editDocument->getRouteKey()])
        ->callAction('post', data: $passwordConfirmation)
        ->assertHasNoActionErrors();

    expect($viewDocument->fresh()->status)->toBe(OpeningInventory::STATUS_POSTED)
        ->and($tableDocument->fresh()->status)->toBe(OpeningInventory::STATUS_POSTED)
        ->and($editDocument->fresh()->status)->toBe(OpeningInventory::STATUS_POSTED);
});

it('uses item UOM assignment for base quantity and amount calculation', function (): void {
    $fixture = openingInventoryWorkflowFixture();
    $carton = UnitOfMeasure::query()->create([
        'uom_code' => 'CT',
        'description' => 'Carton',
        'conversion_factor' => '288.000000000000',
        'is_base_uom' => false,
    ]);
    ItemUomAssignment::query()->create([
        'item_id' => $fixture['item']->id,
        'uom_id' => $carton->id,
        'uom_type' => 'PURCHASE',
        'conversion_factor' => '288.000000000000',
        'is_default' => false,
    ]);

    $document = app(OpeningInventoryService::class)->createDraft(
        documentNumber: 'OPEN-UOM-001',
        source: 'MANUAL',
        postingDate: '2026-08-01',
        lines: [
            openingInventoryWorkflowLine($fixture, quantity: '1.00000000', unitCost: '850.00000000', unitOfMeasureId: $carton->id),
        ],
        businessId: $fixture['business']->id,
    );

    $line = $document->lines()->firstOrFail();

    expect($line->quantity_base)->toBe('288.00000000')
        ->and($line->amount)->toBe('244800.0000');
});

it('enforces business scoped document number uniqueness', function (): void {
    $fixture = openingInventoryWorkflowFixture();
    $otherBusiness = Business::query()->create(['code' => 'OTHER', 'name' => 'Other', 'is_active' => true]);

    openingInventoryWorkflowDocument('OPEN-BUSINESS-001', $fixture);

    expect(fn () => app(OpeningInventoryService::class)->createDraft(
        documentNumber: 'OPEN-BUSINESS-001',
        source: 'MANUAL',
        postingDate: '2026-08-01',
        lines: [openingInventoryWorkflowLine($fixture)],
        businessId: $fixture['business']->id,
    ))->toThrow(ValidationException::class);

    $otherDocument = app(OpeningInventoryService::class)->createDraft(
        documentNumber: 'OPEN-BUSINESS-001',
        source: 'MANUAL',
        postingDate: '2026-08-01',
        lines: [openingInventoryWorkflowLine($fixture)],
        businessId: $otherBusiness->id,
    );

    expect($otherDocument->document_number)->toBe('OPEN-BUSINESS-001')
        ->and($otherDocument->business_id)->toBe($otherBusiness->id);
});

it('rejects new opening inventory documents without a business while legacy null business records remain readable', function (): void {
    $fixture = openingInventoryWorkflowFixture();

    expect(fn () => app(OpeningInventoryService::class)->createDraft(
        documentNumber: 'OPEN-NULL-BUSINESS-001',
        source: 'MANUAL',
        postingDate: '2026-08-01',
        lines: [openingInventoryWorkflowLine($fixture)],
        businessId: null,
    ))->toThrow(ValidationException::class);

    $legacyDocument = OpeningInventory::query()->create([
        'business_id' => null,
        'document_number' => 'LEGACY-NULL-BUSINESS',
        'posting_date' => '2026-08-01',
        'status' => OpeningInventory::STATUS_DRAFT,
        'source' => 'LEGACY',
    ]);

    expect($legacyDocument->fresh()->business_id)->toBeNull()
        ->and(OpeningInventory::query()->whereKey($legacyDocument->id)->exists())->toBeTrue();
});

it('rejects empty documents and invalid line values', function (): void {
    $fixture = openingInventoryWorkflowFixture();

    expect(fn () => app(OpeningInventoryService::class)->createDraft(
        documentNumber: 'OPEN-EMPTY-001',
        source: 'MANUAL',
        postingDate: '2026-08-01',
        lines: [],
        businessId: $fixture['business']->id,
    ))->toThrow(ValidationException::class);

    expect(fn () => app(OpeningInventoryService::class)->createDraft(
        documentNumber: 'OPEN-ZERO-001',
        source: 'MANUAL',
        postingDate: '2026-08-01',
        lines: [openingInventoryWorkflowLine($fixture, quantity: '0.00000000')],
        businessId: $fixture['business']->id,
    ))->toThrow(ValidationException::class);

    expect(fn () => app(OpeningInventoryService::class)->createDraft(
        documentNumber: 'OPEN-COST-001',
        source: 'MANUAL',
        postingDate: '2026-08-01',
        lines: [openingInventoryWorkflowLine($fixture, unitCost: '0.00000000')],
        businessId: $fixture['business']->id,
    ))->toThrow(ValidationException::class);
});

it('posts through item ledger value entry and posting kernel without duplicate direct gl posting', function (): void {
    $fixture = openingInventoryWorkflowFixture();
    $document = openingInventoryWorkflowDocument('OPEN-POST-001', $fixture);

    app(OpeningInventoryService::class)->post($document, $fixture['user']->id);
    app(OpeningInventoryService::class)->post($document->fresh(), $fixture['user']->id);

    $itemLedgerEntry = ItemLedgerEntry::query()->where('source_type', OpeningInventory::class)->where('source_id', $document->id)->firstOrFail();
    $valueEntry = ValueEntry::query()->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)->firstOrFail();
    $debitEntry = GlEntry::query()->with('chartOfAccount')->where('document_number', 'OPEN-POST-001')->where('debit_amount', '>', 0)->firstOrFail();
    $creditEntry = GlEntry::query()->with('chartOfAccount')->where('document_number', 'OPEN-POST-001')->where('credit_amount', '>', 0)->firstOrFail();

    expect(ItemLedgerEntry::query()->where('source_type', OpeningInventory::class)->where('source_id', $document->id)->count())->toBe(1)
        ->and(ValueEntry::query()->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)->count())->toBe(1)
        ->and($valueEntry->gl_posted)->toBeTrue()
        ->and($valueEntry->posting_transaction_id)->not->toBeNull()
        ->and(GlEntry::query()->where('document_number', 'OPEN-POST-001')->count())->toBe(2)
        ->and(GlEntry::query()->where('document_number', 'OPEN-POST-001')->distinct('posting_transaction_id')->count('posting_transaction_id'))->toBe(1)
        ->and($debitEntry->chartOfAccount?->account_number)->toBe('13000')
        ->and($debitEntry->debit_amount)->toBe('25.00')
        ->and($creditEntry->chartOfAccount?->account_number)->toBe('30100')
        ->and($creditEntry->credit_amount)->toBe('25.00')
        ->and($fixture['item']->fresh()->inventory)->toBe('10.00000000');
});

it('recovers partial opening inventory retries without duplicating ledger value or gl entries', function (): void {
    $fixture = openingInventoryWorkflowFixture();
    $document = openingInventoryWorkflowDocument('OPEN-RETRY-001', $fixture);
    $line = $document->lines()->firstOrFail();

    $itemLedgerEntry = ItemLedgerEntry::query()->create([
        'entry_number' => 900001,
        'entry_type' => ItemLedgerEntryType::POSITIVE_ADJUSTMENT,
        'document_type' => 'OPENING_INVENTORY',
        'document_number' => $document->document_number,
        'document_line_number' => $line->line_number,
        'item_id' => $line->item_id,
        'location_id' => $line->location_id,
        'quantity' => $line->quantity_base,
        'remaining_quantity' => $line->quantity_base,
        'open' => true,
        'posting_date' => $document->posting_date,
        'entry_date' => now(),
        'source_type' => OpeningInventory::class,
        'source_id' => $document->id,
        'cost_amount_actual' => $line->amount,
        'cost_amount_expected' => '0.0000',
        'purchase_amount_actual' => '0.0000',
        'general_business_posting_group_id' => GeneralBusinessPostingGroup::query()->where('code', 'OPENING')->value('id'),
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'inventory_posting_group_id' => $fixture['item']->inventory_posting_group_id,
    ]);

    app(OpeningInventoryService::class)->post($document, $fixture['user']->id);

    expect($document->fresh()->status)->toBe(OpeningInventory::STATUS_POSTED)
        ->and($line->fresh()->item_ledger_entry_id)->toBe($itemLedgerEntry->id)
        ->and(ItemLedgerEntry::query()->where('source_type', OpeningInventory::class)->where('source_id', $document->id)->count())->toBe(1)
        ->and(ValueEntry::query()->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)->count())->toBe(1)
        ->and(GlEntry::query()->where('document_number', 'OPEN-RETRY-001')->count())->toBe(2);

    $secondDocument = openingInventoryWorkflowDocument('OPEN-RETRY-002', $fixture);
    $secondLine = $secondDocument->lines()->firstOrFail();
    $secondItemLedgerEntry = ItemLedgerEntry::query()->create([
        'entry_number' => 900002,
        'entry_type' => ItemLedgerEntryType::POSITIVE_ADJUSTMENT,
        'document_type' => 'OPENING_INVENTORY',
        'document_number' => $secondDocument->document_number,
        'document_line_number' => $secondLine->line_number,
        'item_id' => $secondLine->item_id,
        'location_id' => $secondLine->location_id,
        'quantity' => $secondLine->quantity_base,
        'remaining_quantity' => $secondLine->quantity_base,
        'open' => true,
        'posting_date' => $secondDocument->posting_date,
        'entry_date' => now(),
        'source_type' => OpeningInventory::class,
        'source_id' => $secondDocument->id,
        'cost_amount_actual' => $secondLine->amount,
        'cost_amount_expected' => '0.0000',
        'purchase_amount_actual' => '0.0000',
        'general_business_posting_group_id' => GeneralBusinessPostingGroup::query()->where('code', 'OPENING')->value('id'),
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'inventory_posting_group_id' => $fixture['item']->inventory_posting_group_id,
    ]);
    app(ValueEntryService::class)->ensureForItemLedgerEntry($secondItemLedgerEntry);

    app(OpeningInventoryService::class)->post($secondDocument->fresh(), $fixture['user']->id);
    app(OpeningInventoryService::class)->post($secondDocument->fresh(), $fixture['user']->id);

    expect(ItemLedgerEntry::query()->where('source_type', OpeningInventory::class)->where('source_id', $secondDocument->id)->count())->toBe(1)
        ->and(ValueEntry::query()->where('item_ledger_entry_no', $secondItemLedgerEntry->entry_number)->count())->toBe(1)
        ->and(GlEntry::query()->where('document_number', 'OPEN-RETRY-002')->count())->toBe(2)
        ->and($secondDocument->fresh()->status)->toBe(OpeningInventory::STATUS_POSTED);
});

it('keeps draft status after a rolled back opening inventory posting failure', function (): void {
    $fixture = openingInventoryWorkflowFixture();
    GeneralPostingSetup::query()->where('general_product_posting_group_id', $fixture['item']->general_product_posting_group_id)->update([
        'inventory_adj_account_id' => null,
    ]);
    $document = openingInventoryWorkflowDocument('OPEN-ROLLBACK-001', $fixture);

    expect(fn () => app(OpeningInventoryService::class)->post($document, $fixture['user']->id))
        ->toThrow(RuntimeException::class);

    expect($document->fresh()->status)->toBe(OpeningInventory::STATUS_DRAFT)
        ->and(ItemLedgerEntry::query()->where('source_type', OpeningInventory::class)->where('source_id', $document->id)->count())->toBe(0)
        ->and(ValueEntry::query()->where('document_no', 'OPEN-ROLLBACK-001')->count())->toBe(0)
        ->and(GlEntry::query()->where('document_number', 'OPEN-ROLLBACK-001')->count())->toBe(0);
});

it('blocks posted document edits and cancellation', function (): void {
    $fixture = openingInventoryWorkflowFixture();
    $document = openingInventoryWorkflowDocument('OPEN-IMMUTABLE-001', $fixture);

    app(OpeningInventoryService::class)->post($document, $fixture['user']->id);

    expect(fn () => app(OpeningInventoryService::class)->updateDraft($document->fresh(), [
        'description' => 'Not allowed',
    ], [openingInventoryWorkflowLine($fixture)]))->toThrow(RuntimeException::class)
        ->and(fn () => app(OpeningInventoryService::class)->cancelDraft($document->fresh(), $fixture['user']->id))->toThrow(RuntimeException::class)
        ->and(fn () => $document->fresh()->lines()->firstOrFail()->update(['quantity' => '99.00000000']))->toThrow(RuntimeException::class)
        ->and(fn () => $document->fresh()->update(['status' => OpeningInventory::STATUS_DRAFT]))->toThrow(RuntimeException::class);
});

it('cancels draft documents and prevents posting cancelled documents', function (): void {
    $fixture = openingInventoryWorkflowFixture();
    $document = openingInventoryWorkflowDocument('OPEN-CANCEL-001', $fixture);

    $cancelled = app(OpeningInventoryService::class)->cancelDraft($document, $fixture['user']->id);

    expect($cancelled->status)->toBe(OpeningInventory::STATUS_CANCELLED)
        ->and(fn () => app(OpeningInventoryService::class)->post($cancelled->fresh(), $fixture['user']->id))->toThrow(RuntimeException::class)
        ->and(fn () => app(OpeningInventoryService::class)->updateDraft($cancelled->fresh(), ['description' => 'No'], [openingInventoryWorkflowLine($fixture)]))->toThrow(RuntimeException::class);
});

it('blocks direct posting status changes and prevents deleting drafts that already have ledger records', function (): void {
    $fixture = openingInventoryWorkflowFixture();
    $document = openingInventoryWorkflowDocument('OPEN-DIRECT-POST-001', $fixture);
    $line = $document->lines()->firstOrFail();

    expect(fn () => $document->update(['status' => OpeningInventory::STATUS_POSTED]))->toThrow(RuntimeException::class);

    ItemLedgerEntry::query()->create([
        'entry_number' => 900002,
        'entry_type' => ItemLedgerEntryType::POSITIVE_ADJUSTMENT,
        'document_type' => 'OPENING_INVENTORY',
        'document_number' => $document->document_number,
        'document_line_number' => $line->line_number,
        'item_id' => $line->item_id,
        'location_id' => $line->location_id,
        'quantity' => $line->quantity_base,
        'remaining_quantity' => $line->quantity_base,
        'open' => true,
        'posting_date' => $document->posting_date,
        'entry_date' => now(),
        'source_type' => OpeningInventory::class,
        'source_id' => $document->id,
        'cost_amount_actual' => $line->amount,
        'cost_amount_expected' => '0.0000',
        'purchase_amount_actual' => '0.0000',
        'general_business_posting_group_id' => GeneralBusinessPostingGroup::query()->where('code', 'OPENING')->value('id'),
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'inventory_posting_group_id' => $fixture['item']->inventory_posting_group_id,
    ]);

    expect(fn () => $document->fresh()->delete())->toThrow(RuntimeException::class);
});

it('blocks cross business access through the opening inventory policy', function (): void {
    $fixture = openingInventoryWorkflowFixture();
    $otherBusiness = Business::query()->create(['code' => 'BUS-OTHER', 'name' => 'Other Business', 'is_active' => true]);
    $document = openingInventoryWorkflowDocument('OPEN-POLICY-001', $fixture);
    $user = openingInventoryWorkflowSuperAdmin();

    session(['active_business_id' => $otherBusiness->id]);

    expect(app(OpeningInventoryPolicy::class)->view($user, $document))->toBeFalse()
        ->and(app(OpeningInventoryPolicy::class)->update($user, $document))->toBeFalse();
});

it('reports opening value entry accounting ownership gaps', function (): void {
    $fixture = openingInventoryWorkflowFixture();
    $document = openingInventoryWorkflowDocument('OPEN-RECON-001', $fixture);

    app(OpeningInventoryService::class)->post($document, $fixture['user']->id);

    ValueEntry::query()->where('document_no', 'OPEN-RECON-001')->update([
        'gl_posted' => false,
        'posting_transaction_id' => null,
    ]);

    expect(Artisan::call('biwms:inventory-reconcile', ['--json' => true]))->toBe(0);

    $output = Artisan::output();
    $jsonStart = strpos($output, '{');

    expect($jsonStart)->not->toBeFalse();

    $report = json_decode(substr($output, $jsonStart), true, flags: JSON_THROW_ON_ERROR);

    expect($report['opening_value_entry_ownership_gaps'])->not->toBeEmpty()
        ->and($report['opening_value_entry_ownership_gaps'][0]['classification'])->toBe('opening_value_entry_accounting_gap');
});

/**
 * @return array<string, mixed>
 */
function openingInventoryWorkflowFixture(): array
{
    $business = Business::query()->firstOrCreate(['code' => 'BIWMS'], ['name' => 'BIWMS', 'is_active' => true]);
    $user = User::factory()->create();
    $baseUom = UnitOfMeasure::query()->firstOrCreate([
        'uom_code' => 'PCS',
    ], [
        'description' => 'Pieces',
        'conversion_factor' => '1.000000000000',
        'is_base_uom' => true,
    ]);
    $location = Location::factory()->create(['code' => 'OPEN-MAIN', 'name' => 'Opening Main']);

    $generalProductPostingGroup = GeneralProductPostingGroup::query()->firstOrCreate([
        'code' => 'OPENING',
    ], [
        'description' => 'Opening Inventory',
        'blocked' => false,
        'auto_create_vat_prod_posting_group' => false,
    ]);
    $generalBusinessPostingGroup = GeneralBusinessPostingGroup::query()->firstOrCreate([
        'code' => 'OPENING',
    ], [
        'description' => 'Opening Inventory',
        'blocked' => false,
    ]);
    $inventoryPostingGroup = InventoryPostingGroup::query()->firstOrCreate([
        'code' => 'OPENING',
    ], [
        'description' => 'Opening Inventory',
        'blocked' => false,
    ]);

    $inventoryAccount = openingInventoryWorkflowAccount('13000', 'Inventory', AccountCategory::INVENTORY);
    $offsetAccount = openingInventoryWorkflowAccount('30100', 'Opening Balance Equity', AccountCategory::EQUITY);
    $wipAccount = openingInventoryWorkflowAccount('13500', 'WIP', AccountCategory::INVENTORY);

    InventoryPostingSetup::query()->firstOrCreate([
        'location_id' => $location->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
    ], [
        'inventory_account_id' => $inventoryAccount->id,
        'inventory_account_interim_id' => $inventoryAccount->id,
        'wip_account_id' => $wipAccount->id,
    ]);

    GeneralPostingSetup::query()->firstOrCreate([
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
    ], [
        'sales_account_id' => openingInventoryWorkflowAccount('40000', 'Sales', AccountCategory::REVENUE)->id,
        'cogs_account_id' => openingInventoryWorkflowAccount('50000', 'COGS', AccountCategory::COGS)->id,
        'inventory_adj_account_id' => $offsetAccount->id,
        'inventory_account_id' => $inventoryAccount->id,
        'purchase_account_id' => openingInventoryWorkflowAccount('21000', 'Purchase Clearing', AccountCategory::LIABILITY)->id,
        'blocked' => false,
    ]);

    $item = Item::factory()->create([
        'item_code' => 'OPEN-ITEM',
        'description' => 'Opening Item',
        'item_type' => ItemType::RAW_MATERIAL,
        'base_uom_id' => $baseUom->id,
        'location_id' => $location->id,
        'inventory' => '0.00000000',
        'unit_cost' => '2.50000000',
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
    ]);

    ItemUomAssignment::query()->firstOrCreate([
        'item_id' => $item->id,
        'uom_id' => $baseUom->id,
        'uom_type' => 'BASE',
    ], [
        'conversion_factor' => '1.000000000000',
        'is_default' => true,
    ]);

    return compact('business', 'user', 'baseUom', 'location', 'item');
}

function openingInventoryWorkflowDocument(string $documentNumber, ?array $fixture = null): OpeningInventory
{
    $fixture ??= openingInventoryWorkflowFixture();

    return app(OpeningInventoryService::class)->createDraft(
        documentNumber: $documentNumber,
        source: 'MANUAL',
        postingDate: '2026-08-01',
        lines: [openingInventoryWorkflowLine($fixture)],
        businessId: $fixture['business']->id,
        createdBy: $fixture['user']->id,
    );
}

/**
 * @param  array<string, mixed>  $fixture
 * @return array<string, mixed>
 */
function openingInventoryWorkflowLine(
    array $fixture,
    string $quantity = '10.00000000',
    string $unitCost = '2.50000000',
    ?int $unitOfMeasureId = null,
): array {
    return [
        'item_id' => $fixture['item']->id,
        'location_id' => $fixture['location']->id,
        'unit_of_measure_id' => $unitOfMeasureId ?? $fixture['baseUom']->id,
        'quantity' => $quantity,
        'unit_cost' => $unitCost,
    ];
}

/**
 * @param  array<string, mixed>  $fixture
 * @param  array<int, array<string, mixed>>|null  $lines
 * @return array<string, mixed>
 */
function openingInventoryWorkflowFilamentPayload(array $fixture, string $documentNumber, ?array $lines = null): array
{
    return [
        'document_number' => $documentNumber,
        'business_id' => $fixture['business']->id,
        'posting_date' => '2026-08-01',
        'source' => 'MANUAL',
        'description' => 'Filament workflow test',
        'lines' => $lines ?? [
            openingInventoryWorkflowLine($fixture, quantity: '2.00000000'),
            openingInventoryWorkflowLine($fixture, quantity: '3.00000000'),
        ],
    ];
}

function openingInventoryWorkflowAccount(string $number, string $name, AccountCategory $category): ChartOfAccount
{
    return ChartOfAccount::query()->firstOrCreate(
        ['account_number' => $number],
        [
            'name' => $name,
            'structural_type' => AccountStructuralType::POSTING,
            'account_category' => $category,
            'income_balance' => IncomeBalanceType::BALANCE_SHEET,
            'direct_posting' => true,
            'blocked' => false,
            'balance' => 0,
        ],
    );
}

function openingInventoryWorkflowAccountingSetup(): void
{
    GeneralLedgerSetup::query()->firstOrCreate(
        ['company_name' => 'Default Company'],
        [
            'allow_posting_from' => '2026-01-01',
            'allow_posting_to' => '2026-12-31',
        ],
    );

    AccountingPeriod::query()->firstOrCreate([
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ], [
        'name' => 'FY2026',
        'is_closed' => false,
    ]);
}

function openingInventoryWorkflowSuperAdmin(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    (new PermissionsTableSeeder)->run();
    Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole('super_admin');

    expect(Permission::query()->where('name', 'inventory.opening_inventory.post')->exists())->toBeTrue();

    return $user;
}
