<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeIdCards;

use App\Filament\Resources\EmployeeIdCards\Pages\ListEmployeeIdCards;
use App\Filament\Resources\EmployeeIdCards\Pages\ViewEmployeeIdCard;
use App\Models\EmployeeIdCard;
use App\Services\Hr\EmployeeIdCardService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmployeeIdCardResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'employee_id_card';
    }

    protected static ?string $model = EmployeeIdCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'Employee Identity';

    protected static ?string $navigationLabel = 'ID Cards';

    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['employee.department', 'template']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Card')
                ->columns(3)
                ->schema([
                    TextEntry::make('card_number')->label('Card No.'),
                    TextEntry::make('employee.full_name')->label('Employee'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('employee.employee_number')->label('Employee No.'),
                    TextEntry::make('employee.department.name')->label('Department'),
                    TextEntry::make('employee.job_title')->label('Job Title'),
                    TextEntry::make('issue_date')->date(),
                    TextEntry::make('expiry_date')->date(),
                    TextEntry::make('print_count')->numeric(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('card_number')->label('Card No.')->searchable()->sortable(),
                TextColumn::make('employee.employee_number')->label('Emp No.')->searchable()->sortable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(['employee.first_name', 'employee.last_name']),
                TextColumn::make('employee.department.name')->label('Department')->toggleable(),
                TextColumn::make('employee.job_title')->label('Job Title')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        EmployeeIdCard::STATUS_ACTIVE => 'success',
                        EmployeeIdCard::STATUS_DRAFT => 'gray',
                        EmployeeIdCard::STATUS_EXPIRED, EmployeeIdCard::STATUS_REVOKED, EmployeeIdCard::STATUS_LOST => 'danger',
                        EmployeeIdCard::STATUS_REPLACED => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('issue_date')->date()->sortable(),
                TextColumn::make('expiry_date')->date()->sortable(),
                TextColumn::make('print_count')->numeric()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        EmployeeIdCard::STATUS_DRAFT => 'Draft',
                        EmployeeIdCard::STATUS_ACTIVE => 'Active',
                        EmployeeIdCard::STATUS_EXPIRED => 'Expired',
                        EmployeeIdCard::STATUS_LOST => 'Lost',
                        EmployeeIdCard::STATUS_REVOKED => 'Revoked',
                        EmployeeIdCard::STATUS_REPLACED => 'Replaced',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('preview')
                        ->icon('heroicon-o-eye')
                        ->url(fn (EmployeeIdCard $record): string => route('employees.id-card.preview', $record->employee))
                        ->openUrlInNewTab()
                        ->visible(fn (): bool => auth()->user()?->can('hr.employee_id_card.view')),
                    Action::make('print')
                        ->icon('heroicon-o-printer')
                        ->url(fn (EmployeeIdCard $record): string => route('employees.id-card.print', $record->employee))
                        ->openUrlInNewTab()
                        ->visible(fn (): bool => auth()->user()?->can('hr.employee_id_card.download')),
                    Action::make('download')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn (EmployeeIdCard $record): string => route('employees.id-card.download', $record->employee))
                        ->openUrlInNewTab()
                        ->visible(fn (): bool => auth()->user()?->can('hr.employee_id_card.download')),
                    SensitiveActionPasswordConfirmation::protect(
                        Action::make('replace')
                            ->icon('heroicon-o-arrow-path')
                            ->color('warning')
                            ->form([
                                Textarea::make('reason')->label('Reason')->maxLength(500),
                            ])
                            ->visible(fn (EmployeeIdCard $record): bool => $record->status === EmployeeIdCard::STATUS_ACTIVE && auth()->user()?->can('hr.employee_id_card.replace'))
                            ->action(function (EmployeeIdCard $record, array $data): void {
                                app(EmployeeIdCardService::class)->replaceCard($record, $data['reason'] ?? null);
                                Notification::make()->title('Card replaced')->success()->send();
                            })
                    ),
                    SensitiveActionPasswordConfirmation::protect(
                        Action::make('revoke')
                            ->icon('heroicon-o-no-symbol')
                            ->color('danger')
                            ->form([
                                Textarea::make('reason')->label('Reason')->required()->maxLength(500),
                            ])
                            ->visible(fn (EmployeeIdCard $record): bool => $record->status === EmployeeIdCard::STATUS_ACTIVE && auth()->user()?->can('hr.employee_id_card.revoke'))
                            ->action(function (EmployeeIdCard $record, array $data): void {
                                app(EmployeeIdCardService::class)->revokeCard($record, $data['reason'] ?? null);
                                Notification::make()->title('Card revoked')->success()->send();
                            })
                    ),
                    SensitiveActionPasswordConfirmation::protect(
                        Action::make('markLost')
                            ->label('Mark Lost')
                            ->icon('heroicon-o-exclamation-triangle')
                            ->color('danger')
                            ->form([
                                Textarea::make('reason')->label('Reason')->maxLength(500),
                            ])
                            ->visible(fn (EmployeeIdCard $record): bool => $record->status === EmployeeIdCard::STATUS_ACTIVE && auth()->user()?->can('hr.employee_id_card.revoke'))
                            ->action(function (EmployeeIdCard $record, array $data): void {
                                app(EmployeeIdCardService::class)->markLost($record, $data['reason'] ?? null);
                                Notification::make()->title('Card marked lost')->success()->send();
                            })
                    ),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('downloadCards')
                        ->label('Bulk Print/Download')
                        ->icon('heroicon-o-printer')
                        ->visible(fn (): bool => auth()->user()?->can('hr.employee_id_card.download'))
                        ->url(fn (Collection $records): string => route('employees.id-card.bulk-download', [
                            'ids' => $records->pluck('employee_id')->implode(','),
                        ]))
                        ->openUrlInNewTab(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeIdCards::route('/'),
            'view' => ViewEmployeeIdCard::route('/{record}'),
        ];
    }
}
