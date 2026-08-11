<?php

use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\Route;

it('renders business exceptions as validation-style JSON responses', function (): void {
    Route::get('/__test/business-exception-json', fn () => throw new BusinessException(
        message: 'The selected document cannot be posted yet.',
        title: 'Posting blocked',
        field: 'document',
    ));

    $this->getJson('/__test/business-exception-json')
        ->assertStatus(422)
        ->assertJsonPath('message', 'The selected document cannot be posted yet.')
        ->assertJsonPath('title', 'Posting blocked')
        ->assertJsonPath('errors.document.0', 'The selected document cannot be posted yet.');
});

it('renders business exceptions as web validation errors instead of a server error', function (): void {
    Route::middleware('web')->get('/__test/business-exception-web', fn () => throw new BusinessException(
        message: 'Number series setup is incomplete.',
        title: 'Setup required',
        field: 'number_series',
    ));

    $this->from('/login')
        ->get('/__test/business-exception-web')
        ->assertRedirect('/login')
        ->assertSessionHasErrors('number_series');
});
