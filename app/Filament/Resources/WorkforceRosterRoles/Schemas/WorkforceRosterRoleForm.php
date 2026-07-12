<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterRoles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class WorkforceRosterRoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Role Identification')
                    ->icon('heroicon-o-identification')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('code')
                                    ->label('Role Code')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('e.g. CNC-OP-01')
                                    ->prefixIcon('heroicon-m-hashtag'),

                                TextInput::make('name')
                                    ->label('Role Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. CNC Machine Operator')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                        if (empty($get('code')) && ! empty($state)) {
                                            $set('code', strtoupper(str_replace(' ', '-', $state)));
                                        }
                                    }),

                                Select::make('business_id')
                                    ->label('Business')
                                    ->relationship('business', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('department_id', null);
                                        $set('work_center_id', null);
                                    }),
                            ]),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Describe the responsibilities and requirements for this roster role...')
                            ->columnSpanFull(),
                    ]),

                Section::make('Assignment')
                    ->icon('heroicon-o-building-office-2')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('department_id')
                                    ->label('Department')
                                    ->relationship('department', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('work_center_id', null);
                                    }),

                                Select::make('work_center_id')
                                    ->label('Work Center')
                                    ->relationship(
                                        name: 'workCenter',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function (Builder $query, Get $get) {
                                            $departmentId = $get('department_id');
                                            if ($departmentId) {
                                                $query->whereHas('group', function (Builder $q) {
                                                    // Adjust this if your WorkCenterGroup links to departments differently
                                                });
                                            }
                                        }
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->placeholder('Optional work center'),
                            ]),
                    ]),

                Section::make('Status & Flags')
                    ->icon('heroicon-o-flag')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->inline(false)
                            ->default(true)
                            ->onIcon('heroicon-o-check')
                            ->offIcon('heroicon-o-x-mark')
                            ->hint('Inactive roles are hidden from roster assignments'),

                        Toggle::make('is_critical')
                            ->label('Critical Role')
                            ->inline(false)
                            ->default(false)
                            ->onIcon('heroicon-o-exclamation-triangle')
                            ->offIcon('heroicon-o-minus')
                            ->hintIcon('heroicon-m-shield-exclamation')
                            ->hint('Critical roles must always be staffed and trigger alerts if vacant'),
                    ]),
            ]);
    }
}
