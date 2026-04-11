<?php

use App\DTOs\BusinessDTO;
use App\DTOs\FactoryDTO;
use App\Models\Business;
use App\Models\DimensionValue;
use App\Models\Factory;
use App\Services\OrgEntityService;
use Database\Seeders\OrgDimensionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(OrgDimensionSeeder::class);
    $this->service = app(OrgEntityService::class);
});

test('creating a business syncs dimension value', function () {
    $dto = new BusinessDTO('WEST', 'Western Operations');
    $business = $this->service->upsertBusiness($dto);

    expect(Business::count())->toBe(1);

    $dimValue = DimensionValue::whereHas('dimension', fn ($q) => $q->where('code', 'BUSINESS'))
        ->where('code', 'WEST')
        ->first();

    expect($dimValue)->not->toBeNull()
        ->and($dimValue->name)->toBe('Western Operations')
        ->and($dimValue->dimension_value_type->value)->toBe('standard');
});

test('creating a factory assigns correct dimension hierarchy', function () {
    $businessDto = new BusinessDTO('WEST', 'Western Operations');
    $business = $this->service->upsertBusiness($businessDto);

    $factoryDto = new FactoryDTO('DELTA', 'Delta Plant', $business->id);
    $factory = $this->service->upsertFactory($factoryDto);

    expect(Factory::count())->toBe(1);

    $businessDimValue = DimensionValue::whereHas('dimension', fn ($q) => $q->where('code', 'BUSINESS'))
        ->where('code', 'WEST')
        ->first();

    $factoryDimValue = DimensionValue::whereHas('dimension', fn ($q) => $q->where('code', 'FACTORY'))
        ->where('code', 'DELTA')
        ->first();

    expect($factoryDimValue)->not->toBeNull()
        ->and($factoryDimValue->parent_id)->toBe($businessDimValue->id)
        ->and($factoryDimValue->name)->toBe('Delta Plant');
});

test('deleting a business deletes its dimension value', function () {
    $dto = new BusinessDTO('WEST', 'Western Operations');
    $business = $this->service->upsertBusiness($dto);

    $business->delete();

    $dimValue = DimensionValue::whereHas('dimension', fn ($q) => $q->where('code', 'BUSINESS'))
        ->where('code', 'WEST')
        ->first();

    expect($dimValue)->toBeNull();
});
