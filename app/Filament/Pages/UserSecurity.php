<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\AuditTrailService;
use App\Services\Auth\SuperAdminTwoFactorService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class UserSecurity extends Page implements HasTable
{
    use InteractsWithTable;

    // FIXED: Removed trailing space from slug
    protected static ?string $slug = 'user-security';

    protected string $view = 'filament.pages.user-security';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Security';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && (
                $user->hasRole('super_admin')
                || $user->can('user_security.view')
                || $user->can('role_permission.manage')
            );
    }

    protected function canManageUserSecurity(): bool
    {
        $user = Auth::user();

        return $user && (
                $user->hasRole('super_admin')
                || $user->can('user_security.manage')
                || $user->can('role_permission.manage')
            );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => User::query()->with(['roles', 'employee']))
            ->columns($this->getTableColumns())
            ->recordActions($this->getTableActions())
            ->defaultSort('name');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label('User')
                ->searchable()
                ->formatStateUsing(
                    fn (User $record): string => e($record->name)
                        . '<br><small class="text-gray-500 dark:text-gray-400">'
                        . e($record->email)
                        . '</small>'
                )
                ->html(),

            TextColumn::make('roles.name')
                ->label('Roles')
                ->badge()
                ->separator(', '),

            TextColumn::make('employee_status')
                ->label('Employee')
                ->state(fn (User $record): string => ! $record->employee
                    ? '—'
                    : ($record->employee->is_active ? 'Active' : 'Inactive'))
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'Active' => 'success',
                    'Inactive' => 'danger',
                    default => 'gray',
                }),

            TextColumn::make('two_factor_status')
                ->label('2FA')
                ->state(fn (User $record): string => $record->hasConfirmedTwoFactorAuthentication()
                    ? 'Enabled'
                    : 'Disabled')
                ->badge()
                ->color(fn (string $state): string => $state === 'Enabled' ? 'success' : 'warning'),

            TextColumn::make('two_factor_required')
                ->label('Required')
                ->state(fn (User $record): string => $record->requiresTwoFactor() ? 'Yes' : 'No')
                ->badge()
                ->color(fn (string $state): string => $state === 'Yes' ? 'warning' : 'gray'),

            TextColumn::make('two_factor_last_challenged_at')
                ->label('Last Challenge')
                ->dateTime('M j, Y g:i A')
                ->placeholder('—'),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('require_two_factor')
                ->label('Require 2FA')
                ->icon('heroicon-m-shield-exclamation')
                ->visible(fn (User $record): bool => $this->canManageUserSecurity() && ! $record->requiresTwoFactor())
                ->requiresConfirmation()
                ->action(function (User $record): void {
                    $record->forceFill([
                        'two_factor_required' => true,
                    ])->save();

                    app(AuditTrailService::class)->recordGeneric(
                        eventType: 'security',
                        action: 'two_factor_required',
                        auditable: $record,
                        userId: auth()->id(),
                        description: "Required 2FA for user {$record->email}",
                    );

                    Notification::make()
                        ->title('2FA Required')
                        ->success()
                        ->send();
                }),

            Action::make('force_reset')
                ->label('Force Reset')
                ->icon('heroicon-m-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->canManageUserSecurity())
                ->requiresConfirmation()
                ->action(function (User $record): void {
                    app(SuperAdminTwoFactorService::class)->forceReset($record, auth()->id());

                    if ($record->is(auth()->user())) {
                        session()->forget([
                            'super_admin_2fa_setup_secret',
                            'two_factor_passed_at',
                            'super_admin_2fa_passed_at',
                        ]);
                    }

                    Notification::make()
                        ->title('Authenticator Reset')
                        ->success()
                        ->send();
                }),

            Action::make('disable_two_factor')
                ->label('Disable 2FA')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->visible(fn (User $record): bool => $this->canManageUserSecurity()
                    && $record->hasConfirmedTwoFactorAuthentication()
                    && ! $record->requiresTwoFactor())
                ->schema([
                    TextInput::make('confirmation')
                        ->label('Type the user email to confirm')
                        ->required()
                        ->email()
                        ->rule(function (string $attribute, mixed $value, \Closure $fail) use (&$record): void {
                            // We reference $record directly as it's passed down by the table action scope
                            if ((string) $value !== $record->email) {
                                $fail('The email does not match the selected user.');
                            }
                        }),
                ])
                ->action(function (array $data, User $record): void {
                    if ($record->requiresTwoFactor()) {
                        Notification::make()
                            ->title('Cannot disable 2FA')
                            ->body('2FA is required for this account.')
                            ->danger()
                            ->send();

                        return;
                    }

                    app(SuperAdminTwoFactorService::class)->disable($record, auth()->id());

                    if ($record->is(auth()->user())) {
                        session()->forget([
                            'two_factor_passed_at',
                            'super_admin_2fa_passed_at',
                        ]);
                    }

                    app(AuditTrailService::class)->recordGeneric(
                        eventType: 'security',
                        action: 'two_factor_disabled',
                        auditable: $record,
                        userId: auth()->id(),
                        description: "Disabled 2FA for user {$record->email}",
                    );

                    Notification::make()
                        ->title('2FA Disabled')
                        ->success()
                        ->send();
                }),

            Action::make('regenerate_codes')
                ->label('Recovery Codes')
                ->icon('heroicon-m-key')
                ->color('gray')
                ->visible(fn (User $record): bool => $this->canManageUserSecurity()
                    && $record->hasConfirmedTwoFactorAuthentication())
                ->requiresConfirmation()
                ->action(function (User $record) {
                    $codes = app(SuperAdminTwoFactorService::class)
                        ->regenerateRecoveryCodes($record, auth()->id());

                    app(AuditTrailService::class)->recordGeneric(
                        eventType: 'security',
                        action: 'two_factor_recovery_codes_regenerated',
                        auditable: $record,
                        userId: auth()->id(),
                        description: "Regenerated 2FA recovery codes for user {$record->email}",
                        metadata: ['recovery_code_count' => count($codes)],
                    );

                    $filename = 'recovery-codes-' . str($record->email)->replace(['@', '.'], '-') . '-' . now()->timestamp . '.txt';

                    Notification::make()
                        ->title('Recovery Codes Regenerated')
                        ->body('Your browser should download the codes automatically.')
                        ->success()
                        ->send();

                    return response()->streamDownload(
                        fn () => print implode(PHP_EOL, $codes),
                        $filename
                    );
                }),

            Action::make('clear_session')
                ->label('Clear Session')
                ->icon('heroicon-m-arrow-right-on-rectangle')
                ->color('gray')
                ->visible(fn (): bool => $this->canManageUserSecurity())
                ->requiresConfirmation()
                ->action(function (User $record): void {
                    $databaseSessionDriver = config('session.driver') === 'database';
                    $deletedSessions = 0;

                    if ($databaseSessionDriver) {
                        $deletedSessions = DB::table(config('session.table', 'sessions'))
                            ->where('user_id', $record->id)
                            ->delete();
                    }

                    if ($record->is(auth()->user())) {
                        session()->forget([
                            'two_factor_passed_at',
                            'super_admin_2fa_passed_at',
                        ]);
                    }

                    app(AuditTrailService::class)->recordGeneric(
                        eventType: 'security',
                        action: 'two_factor_session_cleared',
                        auditable: $record,
                        userId: auth()->id(),
                        description: "Cleared 2FA/session state for user {$record->email}",
                        metadata: [
                            'database_session_driver' => $databaseSessionDriver,
                            'deleted_sessions' => $deletedSessions,
                        ],
                    );

                    Notification::make()
                        ->title($databaseSessionDriver ? 'Sessions Cleared' : 'Current 2FA State Cleared')
                        ->success()
                        ->send();
                }),
        ];
    }
}
