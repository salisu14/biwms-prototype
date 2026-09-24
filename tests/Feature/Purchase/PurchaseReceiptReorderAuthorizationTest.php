<?php

declare(strict_types=1);

use App\Filament\Resources\PurchaseReceipts\Pages\ViewPurchaseReceipt;
use App\Filament\Resources\PurchaseReceipts\RelationManagers\LinesRelationManager;
use App\Models\Business;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptLine;
use App\Models\User;
use App\Models\Vendor;
use App\Policies\PurchaseReceiptPolicy;
use Database\Seeders\PermissionsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(PermissionsTableSeeder::class);
});

it('allows an update-authorized user to render and reorder an open receipt', function (): void {
    $fixture = receiptReorderFixture();
    $user = receiptReorderUser($fixture['business'], canUpdate: true);

    $component = Livewire::actingAs($user)->test(LinesRelationManager::class, [
        'ownerRecord' => $fixture['receipt'],
        'pageClass' => ViewPurchaseReceipt::class,
    ])->assertSuccessful();

    expect($component->instance()->getTable()->isReorderable())->toBeTrue()
        ->and(app(PurchaseReceiptPolicy::class)->reorder($user))->toBeTrue();

    $component->call('reorderTable', [
        $fixture['secondLine']->getKey(),
        $fixture['firstLine']->getKey(),
    ]);

    expect($fixture['secondLine']->fresh()->line_number)->toBe(1)
        ->and($fixture['firstLine']->fresh()->line_number)->toBe(2);
});

it('renders for a view-only user but denies reorder without mutating lines', function (): void {
    $fixture = receiptReorderFixture();
    $user = receiptReorderUser($fixture['business'], canUpdate: false);

    $component = Livewire::actingAs($user)->test(LinesRelationManager::class, [
        'ownerRecord' => $fixture['receipt'],
        'pageClass' => ViewPurchaseReceipt::class,
    ])->assertSuccessful();

    expect($component->instance()->getTable()->isReorderable())->toBeFalse()
        ->and(app(PurchaseReceiptPolicy::class)->reorder($user))->toBeFalse();

    $component->call('reorderTable', [
        $fixture['secondLine']->getKey(),
        $fixture['firstLine']->getKey(),
    ]);

    expect($fixture['firstLine']->fresh()->line_number)->toBe(10000)
        ->and($fixture['secondLine']->fresh()->line_number)->toBe(20000);
});

it('renders a posted receipt but rejects direct reorder invocation', function (): void {
    $fixture = receiptReorderFixture(posted: true);
    $user = receiptReorderUser($fixture['business'], canUpdate: true);

    $component = Livewire::actingAs($user)->test(LinesRelationManager::class, [
        'ownerRecord' => $fixture['receipt'],
        'pageClass' => ViewPurchaseReceipt::class,
    ])->assertSuccessful();

    expect($component->instance()->getTable()->isReorderable())->toBeFalse()
        ->and(app(PurchaseReceiptPolicy::class)->reorder($user))->toBeTrue();

    $component->call('reorderTable', [
        $fixture['secondLine']->getKey(),
        $fixture['firstLine']->getKey(),
    ]);

    expect($fixture['firstLine']->fresh()->line_number)->toBe(10000)
        ->and($fixture['secondLine']->fresh()->line_number)->toBe(20000);
});

/**
 * @return array{business: Business, receipt: PurchaseReceipt, firstLine: PurchaseReceiptLine, secondLine: PurchaseReceiptLine}
 */
function receiptReorderFixture(bool $posted = false): array
{
    $business = Business::query()->create([
        'code' => 'BUS-REORDER-'.substr(uniqid(), -6),
        'name' => 'Receipt Reorder Business',
        'is_active' => true,
    ]);
    $vendor = Vendor::factory()->create();
    $receipt = PurchaseReceipt::query()->create([
        'business_id' => $business->id,
        'document_number' => 'PR-REORDER-'.substr(uniqid(), -8),
        'vendor_id' => $vendor->id,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'currency_code' => 'NGN',
        'exchange_rate' => 1,
        'status' => $posted ? 'POSTED' : 'OPEN',
        'posted' => $posted,
        'posted_at' => $posted ? now() : null,
    ]);

    $firstLine = receiptReorderLine($receipt, 10000, 'First line');
    $secondLine = receiptReorderLine($receipt, 20000, 'Second line');

    return compact('business', 'receipt', 'firstLine', 'secondLine');
}

function receiptReorderUser(Business $business, bool $canUpdate): User
{
    $user = User::factory()->create();
    $permissions = [
        'procurement.purchase_receipt.view_any',
        'procurement.purchase_receipt.view',
    ];

    if ($canUpdate) {
        $permissions[] = 'procurement.purchase_receipt.update';
    }

    $user->givePermissionTo($permissions);
    $user->businesses()->attach($business->id, ['granted_by' => $user->id]);
    session(['active_business_id' => $business->id]);

    return $user;
}

function receiptReorderLine(PurchaseReceipt $receipt, int $lineNumber, string $description): PurchaseReceiptLine
{
    return PurchaseReceiptLine::query()->create([
        'purchase_receipt_id' => $receipt->id,
        'line_number' => $lineNumber,
        'type' => 'ITEM',
        'no' => 'ITEM-'.$lineNumber,
        'description' => $description,
        'quantity' => 1,
        'direct_unit_cost' => 10,
        'line_amount' => 10,
    ]);
}
