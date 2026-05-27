<?php

use App\Models\SalesQuote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('enforces sales quote view any permission through policy', function () {
    $user = User::factory()->create();

    Permission::findOrCreate('view:any:sales_quote', 'web');
    $user->givePermissionTo('view:any:sales_quote');

    expect($user->can('viewAny', SalesQuote::class))->toBeTrue();
});

it('blocks sales quote conversion when required state is missing', function () {
    $quote = new SalesQuote([
        'status' => 'draft',
        'approval_status' => 'pending',
    ]);

    expect($quote->canConvertToOrder())->toBeFalse();
});
