<?php

declare(strict_types=1);

namespace App\Filament\Resources\Employees\Tables;

use App\Models\Employee;
use App\Services\Hr\EmployeeIdCardService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50])
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Photo')
                    ->disk('public')
                    ->imageSize(44)
                    ->circular()
                    ->checkFileExistence(false)
                    ->defaultImageUrl(asset('images/employee-placeholder.svg'))
                    ->extraImgAttributes([
                        'loading' => 'lazy',
                        'decoding' => 'async',
                        'alt' => 'Employee photo',
                    ])
                    ->toggleable(),
                TextColumn::make('employee_number')
                    ->label('No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label('Employee')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('department_code')
                    ->label('Department')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn(bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn(bool $state): string => $state ? 'success' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('generateIdCard')
                        ->label('Generate ID Card')
                        ->icon('heroicon-o-identification')
                        ->color('info')
                        ->visible(fn(Employee $record): bool => blank($record->id_card_token) && auth()->user()?->can('hr.employee_id_card.generate'))
                        ->action(function (Employee $record): void {
                            app(EmployeeIdCardService::class)->issueCard($record);

                            Notification::make()
                                ->title('Employee ID card generated')
                                ->success()
                                ->send();
                        }),
                    SensitiveActionPasswordConfirmation::protect(
                        Action::make('regenerateIdCard')
                            ->label('Regenerate ID Card')
                            ->icon('heroicon-o-arrow-path')
                            ->color('warning')
                            ->visible(fn(Employee $record): bool => filled($record->id_card_token) && auth()->user()?->can('hr.employee_id_card.regenerate'))
                            ->action(function (Employee $record): void {
                                app(EmployeeIdCardService::class)->replaceCard($record, 'Regenerated from employee shortcut.');

                                Notification::make()
                                    ->title('Employee ID card regenerated')
                                    ->success()
                                    ->send();
                            })
                    ),
                    Action::make('downloadIdCard')
                        ->label('Download ID Card PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn(Employee $record): string => route('employees.id-card.download', $record))
                        ->openUrlInNewTab()
                        ->visible(fn(Employee $record): bool => filled($record->id_card_token) && auth()->user()?->can('hr.employee_id_card.download')),
                ])
            ]);
    }
}
