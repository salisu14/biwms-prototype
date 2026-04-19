<?php

namespace App\Filament\Resources\ApprovalTemplates\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApprovalTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Identification')
                    ->description('Primary naming and status for the approval workflow.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('code')
                            ->label('Template Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->placeholder('e.g., PO_APPROVAL'),

                        TextInput::make('description')
                            ->label('Description')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2)
                            ->placeholder('e.g., Standard Purchase Order Approval Workflow'),

                        Toggle::make('enabled')
                            ->label('Workflow Active')
                            ->helperText('If disabled, documents will skip this approval logic.')
                            ->default(true)
                            ->inline(false),
                    ]),

                Section::make('Trigger Conditions')
                    ->description('Define what document types and values trigger this approval.')
                    ->columns(2)
                    ->schema([
                        Select::make('document_type')
                            ->label('Document Type')
                            ->options([
                                'Purchase Order' => 'Purchase Order',
                                'Sales Order' => 'Sales Order',
                                'Expense Voucher' => 'Expense Voucher',
                                'Journal' => 'General Journal',
                            ])
                            ->required()
                            ->native(false),

                        TextInput::make('amount_limit')
                            ->label('Min. Amount Limit')
                            ->numeric()
                            ->prefix('$')
                            ->helperText('Trigger approval if the document amount exceeds this value.')
                            ->placeholder('0.00'),
                    ]),

                Section::make('Filtering Logic')
                    ->description('Restrict this workflow to specific segments of the business.')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Select::make('vendor_posting_group_filter')
                            ->label('Vendor Posting Group Filter')
                            ->relationship('vendorPostingGroup', 'code')
                            ->searchable()
                            ->preload()
                            ->placeholder('All Groups'),

                        TextInput::make('location_filter')
                            ->label('Location Filter')
                            ->placeholder('e.g., MAIN, WAREHOUSE'),

                        Select::make('dimension_1_filter')
                            ->label('Department Filter (Dim 1)')
                            ->multiple()
                            ->options([]) // Map to your dimension values
                            ->placeholder('All Departments'),

                        Select::make('dimension_2_filter')
                            ->label('Project Filter (Dim 2)')
                            ->multiple()
                            ->options([]) // Map to your dimension values
                            ->placeholder('All Projects'),

                        TextInput::make('due_date_formula')
                            ->label('Approval Due Date Formula (Days)')
                            ->numeric()
                            ->suffix('Days')
                            ->default(2),
                    ]),
            ]);
    }
}
