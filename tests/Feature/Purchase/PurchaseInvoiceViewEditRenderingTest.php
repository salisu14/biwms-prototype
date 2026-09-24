<?php

declare(strict_types=1);

use App\Enums\ApprovalStatus;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\Business;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionsTableSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(PermissionsTableSeeder::class);
});

it('resolves the purchase invoice resource query without the removed relationships', function (): void {
    $fixture = purchaseInvoiceRenderFixture();

    $eagerLoads = PurchaseInvoiceResource::getEloquentQuery()->getEagerLoads();

    expect($eagerLoads)->not->toHaveKey('capExProject')
        ->and($eagerLoads)->not->toHaveKey('payableAccount')
        ->and($eagerLoads)->not->toHaveKey('requester')
        ->and($eagerLoads)->toHaveKey('vendor')
        ->and($eagerLoads)->toHaveKey('purchaseOrder')
        ->and($eagerLoads)->toHaveKey('location')
        ->and($eagerLoads)->toHaveKey('approver')
        ->and($eagerLoads)->toHaveKey('poster');

    // Executes without RelationNotFoundException.
    $found = PurchaseInvoiceResource::getEloquentQuery()->whereKey($fixture['invoice']->getKey())->first();

    expect($found?->getKey())->toBe($fixture['invoice']->getKey());
});

it('executes the global search query without the removed relationships', function (): void {
    purchaseInvoiceRenderFixture();

    $query = PurchaseInvoiceResource::getGlobalSearchEloquentQuery();
    $eagerLoads = $query->getEagerLoads();

    expect($eagerLoads)->not->toHaveKey('capExProject')
        ->and($eagerLoads)->not->toHaveKey('payableAccount')
        ->and($eagerLoads)->not->toHaveKey('requester');

    // Executes without RelationNotFoundException.
    expect($query->get())->toBeInstanceOf(Collection::class);
});

it('renders the purchase invoice view page for an unposted invoice', function (): void {
    $fixture = purchaseInvoiceRenderFixture();
    $fixture['user']->givePermissionTo([
        'procurement.purchase_invoice.view_any',
        'procurement.purchase_invoice.view',
    ]);

    $this->actingAs($fixture['user'])
        ->get("/admin/purchase-invoices/{$fixture['invoice']->getKey()}")
        ->assertSuccessful();
});

it('renders the purchase invoice edit page for an editable unposted invoice', function (): void {
    $fixture = purchaseInvoiceRenderFixture();
    $fixture['user']->givePermissionTo([
        'procurement.purchase_invoice.view_any',
        'procurement.purchase_invoice.view',
        'procurement.purchase_invoice.update',
    ]);

    $this->actingAs($fixture['user'])
        ->get("/admin/purchase-invoices/{$fixture['invoice']->getKey()}/edit")
        ->assertSuccessful();
});

/**
 * @return array{user: User, business: Business, vendor: Vendor, invoice: PurchaseInvoice}
 */
function purchaseInvoiceRenderFixture(): array
{
    $user = User::factory()->create();
    $business = Business::query()->create([
        'code' => 'BUS-PIRE-'.substr(uniqid(), -6),
        'name' => 'PI Render Business',
        'is_active' => true,
    ]);
    $vendor = Vendor::factory()->create();
    $user->businesses()->attach($business->id, ['granted_by' => $user->id]);
    session(['active_business_id' => $business->id]);

    $invoice = PurchaseInvoice::query()->create([
        'business_id' => $business->id,
        'document_number' => 'PI-TEST-'.substr(uniqid(), -6),
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'due_date' => now()->toDateString(),
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'status' => ApprovalStatus::DRAFT,
        'total_amount' => 1000,
        'total_vat' => 0,
        'grand_total' => 1000,
        'amount_paid' => 0,
        'remaining_amount' => 1000,
        'total_amount_lcy' => 1000,
        'total_vat_lcy' => 0,
        'grand_total_lcy' => 1000,
        'remaining_amount_lcy' => 1000,
    ]);

    return compact('user', 'business', 'vendor', 'invoice');
}
