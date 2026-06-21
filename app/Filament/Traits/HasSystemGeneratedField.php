<?php

declare(strict_types=1);

namespace App\Filament\Traits;

use Filament\Forms\Components\TextInput;

trait HasSystemGeneratedField
{
    protected static function makeSystemGeneratedTextInput(
        string $name,
        string $label,
        string $helperText,
        string $placeholder = 'Auto-generated on save'
    ): TextInput {
        return TextInput::make($name)
            ->label($label)
            ->placeholder($placeholder)
            ->disabled()
            ->dehydrated(false)
            ->hint('System-generated')
            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
            ->helperText($helperText);
    }
}
