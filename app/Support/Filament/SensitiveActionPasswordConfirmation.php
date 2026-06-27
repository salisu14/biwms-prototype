<?php

namespace App\Support\Filament;

use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use ReflectionClass;

class SensitiveActionPasswordConfirmation
{
    public const FIELD = 'security_password_confirmation';

    /**
     * @var array<int, string>
     */
    private const SENSITIVE_ACTION_NAMES = [
        'delete',
        'forceDelete',
        'post',
        'post_shipment',
        'post_and_invoice',
        'post_receipt',
        'finish',
        'void',
        'reverse',
        'reconcile',
        'markReconciled',
        'undoReconciled',
        'require_two_factor',
        'force_reset',
        'disable_two_factor',
        'regenerate_codes',
        'clear_session',
    ];

    public static function configureAction(Action $action): void
    {
        if (! static::isSensitiveAction($action)) {
            return;
        }

        static::protect($action);
    }

    public static function protect(Action $action): Action
    {
        $action->requiresConfirmation();
        $action->modalDescription(static::descriptionFor($action));
        $action->schema(static::schemaWithPasswordConfirmation(static::rawSchema($action)));

        return $action;
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component|\Filament\Actions\Action|\Filament\Actions\ActionGroup>|\Closure|null
     */
    public static function schemaWithPasswordConfirmation(array|Closure|null $schema): array|Closure|null
    {
        if ($schema instanceof Closure) {
            return $schema;
        }

        return [
            ...($schema ?? []),
            static::passwordField(),
        ];
    }

    public static function passwordField(): TextInput
    {
        return TextInput::make(static::FIELD)
            ->label('Confirm your password')
            ->password()
            ->currentPassword(guard: Filament::getAuthGuard())
            ->required()
            ->revealable(filament()->arePasswordsRevealable())
            ->dehydrated(false)
            ->helperText('Required for destructive or security-sensitive actions.');
    }

    private static function isSensitiveAction(Action $action): bool
    {
        return in_array($action->getName(), self::SENSITIVE_ACTION_NAMES, true);
    }

    private static function descriptionFor(Action $action): string
    {
        return match ($action->getName()) {
            'delete', 'forceDelete' => 'Confirm your password before permanently changing or removing records.',
            default => 'Confirm your password before continuing with this sensitive action.',
        };
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component|\Filament\Actions\Action|\Filament\Actions\ActionGroup>|\Closure|null
     */
    private static function rawSchema(Action $action): array|Closure|null
    {
        $reflection = new ReflectionClass($action);

        while (! $reflection->hasProperty('schema') && ($parent = $reflection->getParentClass())) {
            $reflection = $parent;
        }

        if (! $reflection->hasProperty('schema')) {
            return null;
        }

        $property = $reflection->getProperty('schema');
        $property->setAccessible(true);

        /** @var array<int, \Filament\Schemas\Components\Component|\Filament\Actions\Action|\Filament\Actions\ActionGroup>|\Closure|null $schema */
        $schema = $property->getValue($action);

        return $schema;
    }
}
