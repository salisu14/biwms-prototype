<?php

use App\Models\Item;
use App\Models\PriceChangeTemplate;
use App\Models\PriceChangeTemplateLine;
use App\Services\Inventory\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('price change template applies header calculation to specific finished good lines', function () {
    $item = Item::factory()->create([
        'item_type' => 'FINISHED_GOOD',
        'unit_cost' => 100,
        'unit_price' => 170,
    ]);

    $template = PriceChangeTemplate::query()->create([
        'name' => 'Inflation Increase',
        'adjustment_type' => 'increase',
        'value' => 20,
        'base' => 'cost',
        'rounding' => 2,
        'status' => 'approved',
    ]);

    $line = PriceChangeTemplateLine::query()->create([
        'template_id' => $template->id,
        'item_id' => $item->id,
    ]);

    $updatedCount = app(ItemService::class)->applyPriceTemplate($template);

    $item->refresh();
    $line->refresh();
    $template->refresh();

    expect($updatedCount)->toBe(1)
        ->and((float) $item->unit_price)->toBe(120.0)
        ->and((float) $line->current_unit_price)->toBe(170.0)
        ->and((float) $line->new_unit_price)->toBe(120.0)
        ->and((float) $line->adjustment_amount)->toBe(-50.0)
        ->and((float) $line->adjustment_percent)->toEqualWithDelta(-29.4118, 0.0001)
        ->and($line->applied_at)->not->toBeNull()
        ->and($template->status)->toBe('applied');
});
