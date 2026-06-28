<?php

use App\Filament\Resources\BankAccounts\BankAccountResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Vendors\VendorResource;
use App\Filament\Resources\WarehouseReceipts\WarehouseReceiptResource;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Location;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WarehouseReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

it('blocks direct integer-id access to representative Filament resources without permissions', function (string $resourceClass, Closure $recordFactory): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $record = $recordFactory();

    $this->get($resourceClass::getUrl('view', ['record' => $record]))
        ->assertNotFound();

    $this->get($resourceClass::getUrl('edit', ['record' => $record]))
        ->assertNotFound();

    expect(Gate::forUser($user)->allows('delete', $record))->toBeFalse();
})->with([
    'admin user' => [
        UserResource::class,
        fn (): User => User::factory()->create(),
    ],
    'finance bank account' => [
        BankAccountResource::class,
        fn (): BankAccount => BankAccount::factory()->create(),
    ],
    'sales customer' => [
        CustomerResource::class,
        fn (): Customer => Customer::factory()->create(),
    ],
    'procurement vendor' => [
        VendorResource::class,
        fn (): Vendor => Vendor::factory()->create(),
    ],
    'factory production order' => [
        ProductionOrderResource::class,
        fn (): ProductionOrder => ProductionOrder::query()->create([
            'document_number' => 'PO-IDOR-001',
            'status' => 'FIRM_PLANNED',
            'item_id' => Item::factory()->create()->id,
            'quantity' => 1,
            'quantity_base' => 1,
            'unit_of_measure_code' => 'PCS',
            'created_by' => User::factory()->create()->id,
        ]),
    ],
    'warehouse receipt' => [
        WarehouseReceiptResource::class,
        fn (): WarehouseReceipt => WarehouseReceipt::query()->create([
            'document_number' => 'WR-IDOR-001',
            'location_id' => Location::factory()->create()->id,
            'source_document' => 'PURCHASE_ORDER',
            'source_document_id' => 1,
            'source_document_number' => 'PO-IDOR-001',
            'vendor_id' => Vendor::factory()->create()->id,
            'status' => 'OPEN',
            'receipt_date' => now()->toDateString(),
        ]),
    ],
]);
