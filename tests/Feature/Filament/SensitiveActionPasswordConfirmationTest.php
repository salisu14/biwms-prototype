<?php

use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Schemas\Schema;

it('adds password confirmation to sensitive action schemas', function (): void {
    $schema = SensitiveActionPasswordConfirmation::schemaWithPasswordConfirmation([]);

    expect($schema)->toHaveCount(1)
        ->and($schema[0]->getName())->toBe(SensitiveActionPasswordConfirmation::FIELD);
});

it('globally protects destructive and posting actions', function (): void {
    expect(DeleteAction::make()->getSchema(app(Schema::class)))->not->toBeNull()
        ->and(Action::make('post')->getSchema(app(Schema::class)))->not->toBeNull()
        ->and(Action::make('open')->getSchema(app(Schema::class)))->toBeNull();
});
