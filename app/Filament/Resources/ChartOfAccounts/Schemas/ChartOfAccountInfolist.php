<?php

declare(strict_types=1);

namespace App\Filament\Resources\ChartOfAccounts\Schemas;

use App\Enums\IncomeBalanceType;
use BackedEnum;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ChartOfAccountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            self::makeMainGrid(),
        ]);
    }

    private static function makeMainGrid(): Grid
    {
        return Grid::make([
            'default' => 1,
            'lg' => 2,
        ])->schema([
            self::makeAccountOverviewColumn(),
            self::makePostingAndReportingColumn(),
        ]);
    }

    // ==================== COLUMN 1: ACCOUNT OVERVIEW ====================

    private static function makeAccountOverviewColumn(): Group
    {
        return Group::make([
            self::makeGeneralInformationSection(),
            self::makeHierarchySection(),
            self::makeFinancialStatusSection(),
        ]);
    }

    private static function makeGeneralInformationSection(): Section
    {
        return Section::make('General Information')
            ->icon('heroicon-o-identification')
            ->schema([
                Grid::make([
                    'default' => 1,
                    'md' => 2,
                ])->schema([
                    self::makeAccountNumberEntry(),
                    self::makeNameEntry(),
                    self::makeCategoryEntry(),
                    self::makeStructuralTypeEntry(),
                    self::makeIncomeBalanceEntry(),
                ]),
            ]);
    }

    private static function makeAccountNumberEntry(): TextEntry
    {
        return TextEntry::make('account_number')
            ->label('Account No.')
            ->weight('bold')
            ->copyable()
            ->copyMessage('Account number copied!')
            ->copyMessageDuration(2000);
    }

    private static function makeNameEntry(): TextEntry
    {
        return TextEntry::make('name')
            ->weight('bold')
            ->columnSpanFull();
    }

    private static function makeCategoryEntry(): TextEntry
    {
        return TextEntry::make('account_category')
            ->label('Category')
            ->badge()
            ->formatStateUsing(fn (mixed $state): string => self::stateLabel($state))
            ->color(fn (mixed $state): string => match (self::stateValue($state)) {
                'asset', 'liquid_asset', 'receivable', 'inventory', 'fixed_asset' => 'primary',
                'liability', 'payable' => 'warning',
                'equity' => 'info',
                'revenue' => 'success',
                'cogs', 'direct_expense', 'indirect_expense', 'operating_expense', 'other_income_expense' => 'danger',
                default => 'gray',
            });
    }

    private static function makeStructuralTypeEntry(): TextEntry
    {
        return TextEntry::make('structural_type')
            ->label('Type')
            ->badge()
            ->formatStateUsing(fn (mixed $state): string => self::stateLabel($state))
            ->color(fn (mixed $state): string => match (self::stateValue($state)) {
                'heading' => 'gray',
                'total', 'begin_total', 'end_total' => 'info',
                'posting' => 'success',
                default => 'gray',
            })
            ->icon(fn (mixed $state): ?string => match (self::stateValue($state)) {
                'posting' => 'heroicon-o-pencil-square',
                'total', 'begin_total', 'end_total' => 'heroicon-o-calculator',
                'heading' => 'heroicon-o-folder',
                default => null,
            });
    }

    private static function makeIncomeBalanceEntry(): TextEntry
    {
        return TextEntry::make('income_balance')
            ->label('Financial Statement')
            ->badge()
            ->formatStateUsing(fn (mixed $state): string => self::incomeBalanceLabel($state))
            ->color(fn (mixed $state): string => self::isBalanceSheetState($state) ? 'gray' : 'success')
            ->icon(fn (mixed $state): ?string => match (self::stateValue($state)) {
                IncomeBalanceType::BALANCE_SHEET->value, '0' => 'heroicon-o-scale',
                IncomeBalanceType::INCOME_STATEMENT->value, '1' => 'heroicon-o-chart-bar',
                default => null,
            });
    }

    private static function makeHierarchySection(): Section
    {
        return Section::make('Account Hierarchy')
            ->icon('heroicon-o-squares-2x2')
            ->collapsed()
            ->schema([
                Grid::make(1)->schema([
                    self::makeParentAccountEntry(),
                    self::makeIndentationEntry(),
                    self::makeTotalingEntry(),
                ]),
            ]);
    }

    private static function makeParentAccountEntry(): TextEntry
    {
        return TextEntry::make('parentAccount.name')
            ->label('Parent Account')
            ->placeholder('─ Root Level Account ─')
            ->icon('heroicon-o-folder-open');
    }

    private static function makeIndentationEntry(): TextEntry
    {
        return TextEntry::make('indentation')
            ->label('Indentation Level')
            ->formatStateUsing(function ($state) {
                $level = (int) $state;
                if ($level === 0) {
                    return 'None (Level 0)';
                }

                $indent = str_repeat('▸ ', $level);

                return "{$indent}Level {$level}";
            })
            ->placeholder('None');
    }

    private static function makeTotalingEntry(): TextEntry
    {
        return TextEntry::make('totaling')
            ->label('Totaling Formula')
            ->placeholder('─ No totaling formula ─')
            ->fontFamily('mono')
            ->copyable()
            ->visible(fn ($record) => ! empty($record?->totaling));
    }

    // ==================== COLUMN 2: POSTING, REPORTING & SYSTEM DETAILS ====================

    private static function makePostingAndReportingColumn(): Group
    {
        return Group::make([
            self::makePostingGroupsSection(),
            self::makePostingControlsSection(),
            self::makeReportingLayoutSection(),
            self::makeSystemDetailsSection(),
        ]);
    }

    private static function makePostingGroupsSection(): Section
    {
        return Section::make('Posting Groups Configuration')
            ->description('Define how this account interacts with posting groups')
            ->icon('heroicon-o-cog-6-tooth')
            ->schema([
                Grid::make([
                    'default' => 1,
                    'md' => 2,
                ])->schema([
                    self::makeGenBusPostingGroupEntry(),
                    self::makeGenProdPostingGroupEntry(),
                    self::makeVatBusPostingGroupEntry(),
                    self::makeVatProdPostingGroupEntry(),
                ]),
            ]);
    }

    private static function makeGenBusPostingGroupEntry(): TextEntry
    {
        return self::makePostingGroupBadge(
            'genBusPostingGroup.code',
            'Gen. Bus. Group',
            'heroicon-o-users'
        );
    }

    private static function makeGenProdPostingGroupEntry(): TextEntry
    {
        return self::makePostingGroupBadge(
            'genProdPostingGroup.code',
            'Gen. Prod. Group',
            'heroicon-o-cube'
        );
    }

    private static function makeVatBusPostingGroupEntry(): TextEntry
    {
        return self::makePostingGroupBadge(
            'vatBusPostingGroup.code',
            'VAT Bus. Group',
            'heroicon-o-building-office'
        );
    }

    private static function makeVatProdPostingGroupEntry(): TextEntry
    {
        return self::makePostingGroupBadge(
            'vatProdPostingGroup.code',
            'VAT Prod. Group',
            'heroicon-o-tag'
        );
    }

    private static function makePostingGroupBadge(
        string $field,
        string $label,
        string $icon
    ): TextEntry {
        return TextEntry::make($field)
            ->label($label)
            ->badge()
            ->color('warning')
            ->icon($icon)
            ->placeholder('—')
            ->formatStateUsing(fn ($state) => $state ?? 'Not Set');
    }

    private static function makePostingControlsSection(): Section
    {
        return Section::make('Posting Controls & Restrictions')
            ->icon('heroicon-o-shield-check')
            ->schema([
                Grid::make([
                    'default' => 1,
                    'md' => 2,
                ])->schema([
                    self::makeDirectPostingEntry(),
                    self::makeBlockedEntry(),
                ]),

                Grid::make([
                    'default' => 1,
                    'md' => 2,
                ])->schema([
                    self::makeBlockedFromEntry(),
                    self::makeBlockedToEntry(),
                ])->visible(fn ($record) => $record?->blocked),
            ]);
    }

    private static function makeDirectPostingEntry(): IconEntry
    {
        return IconEntry::make('direct_posting')
            ->label('Direct Posting Allowed')
            ->boolean()
            ->trueIcon('heroicon-o-check-circle')
            ->falseIcon('heroicon-o-x-circle')
            ->trueColor('success')
            ->falseColor('gray')
            ->tooltip('Allows direct journal entries to this account');
    }

    private static function makeBlockedEntry(): IconEntry
    {
        return IconEntry::make('blocked')
            ->label('Account Blocked')
            ->boolean()
            ->trueIcon('heroicon-o-lock-closed')
            ->falseIcon('heroicon-o-lock-open')
            ->trueColor('danger')
            ->falseColor('success')
            ->tooltip('Prevents all postings to this account');
    }

    private static function makeBlockedFromEntry(): TextEntry
    {
        return TextEntry::make('blocked_from')
            ->label('Block Start Date')
            ->date()
            ->icon('heroicon-o-calendar')
            ->placeholder('—');
    }

    private static function makeBlockedToEntry(): TextEntry
    {
        return TextEntry::make('blocked_to')
            ->label('Block End Date')
            ->date()
            ->icon('heroicon-o-calendar-days')
            ->placeholder('—');
    }

    private static function makeReportingLayoutSection(): Section
    {
        return Section::make('Reporting & Layout Options')
            ->icon('heroicon-o-document-text')
            ->schema([
                Grid::make([
                    'default' => 1,
                    'md' => 2,
                ])->schema([
                    self::makeNewPageEntry(),
                    self::makeBlankLinesEntry(),
                ]),

                self::makeTypographyDivider(),

                Grid::make([
                    'default' => 2,
                    'md' => 4,
                ])->schema([
                    self::makeBoldEntry(),
                    self::makeItalicEntry(),
                    self::makeUnderlineEntry(),
                    self::makeOppositeSignEntry(),
                ]),
            ]);
    }

    private static function makeNewPageEntry(): IconEntry
    {
        return IconEntry::make('new_page')
            ->label('Page Break Before')
            ->boolean()
            ->trueIcon('heroicon-o-document-plus')
            ->falseIcon('heroicon-o-document')
            ->trueColor('primary')
            ->falseColor('gray');
    }

    private static function makeBlankLinesEntry(): TextEntry
    {
        return TextEntry::make('no_of_blank_lines')
            ->label('Blank Lines After')
            ->formatStateUsing(fn ($state): string => ((int) ($state ?? 0)).' line(s)')
            ->icon('heroicon-o-arrows-up-down')
            ->placeholder('0 lines');
    }

    private static function makeTypographyDivider(): TextEntry
    {
        return TextEntry::make('typography_header')
            ->label('Typography Style')
            ->hiddenLabel()
            ->columnSpanFull()
            ->formatStateUsing(fn () => '**Font Styling**')
            ->markdown()
            ->size('sm')
            ->weight('medium');
    }

    private static function makeBoldEntry(): IconEntry
    {
        return IconEntry::make('bold')
            ->label('Bold')
            ->boolean()
            ->trueIcon('heroicon-o-bold')
            ->trueColor('default');
    }

    private static function makeItalicEntry(): IconEntry
    {
        return IconEntry::make('italic')
            ->label('Italic')
            ->boolean()
            ->trueIcon('heroicon-o-italic')
            ->trueColor('default');
    }

    private static function makeUnderlineEntry(): IconEntry
    {
        return IconEntry::make('underline')
            ->label('Underline')
            ->boolean()
            ->trueIcon('heroicon-o-underline')
            ->trueColor('default');
    }

    private static function makeOppositeSignEntry(): IconEntry
    {
        return IconEntry::make('show_opposite_sign')
            ->label('Opposite Sign')
            ->boolean()
            ->trueIcon('heroicon-o-arrows-right-left')
            ->trueColor('warning')
            ->tooltip('Display balance with opposite sign on reports');
    }

    private static function makeFinancialStatusSection(): Section
    {
        return Section::make('Financial Status')
            ->icon('heroicon-o-currency-dollar')
            ->schema([
                self::makeBalanceEntry(),
                self::makeBalanceAtDateEntry(),
                self::makeAccountStatusAlert(),
            ]);
    }

    private static function makeBalanceEntry(): TextEntry
    {
        return TextEntry::make('balance')
            ->label('Current Balance')
            ->money('NGN')
            ->size('xl')
            ->weight('bold')
            ->icon('heroicon-o-banknotes')
            ->color(fn ($state) => match (true) {
                $state < 0 => 'danger',
                $state > 0 => 'success',
                default => 'gray',
            })
            ->formatStateUsing(function ($state) {
                $amount = number_format(abs((float) $state), 2);
                $prefix = (float) $state < 0 ? '(₦' : '₦';
                $suffix = (float) $state < 0 ? ')' : '';

                return "{$prefix}{$amount}{$suffix}";
            });
    }

    private static function makeBalanceAtDateEntry(): TextEntry
    {
        return TextEntry::make('balance_at_date')
            ->label('Balance at Report Date')
            ->money('NGN')
            ->size('lg')
            ->icon('heroicon-o-calendar-date-range')
            ->color('gray')
            ->placeholder('No report date calculated');
    }

    private static function makeAccountStatusAlert(): TextEntry
    {
        return TextEntry::make('account_status_summary')
            ->label('Account Status')
            ->hiddenLabel()
            ->columnSpanFull()
            ->state(fn ($record): ?string => self::accountStatusSummary($record))
            ->badge()
            ->color(fn ($record): string => self::accountStatusColor($record));
    }

    private static function makeSystemDetailsSection(): Section
    {
        return Section::make('System Information')
            ->icon('heroicon-o-information-circle')
            ->collapsed()
            ->schema([
                Grid::make([
                    'default' => 1,
                    'md' => 2,
                ])->schema([
                    self::makeCreatedAtEntry(),
                    self::makeUpdatedAtEntry(),
                ]),

                Grid::make([
                    'default' => 1,
                    'md' => 2,
                ])->schema([
                    self::makeSearchNameEntry(),
                    self::makeAccountIdEntry(),
                ]),
            ]);
    }

    private static function makeCreatedAtEntry(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label('Created At')
            ->dateTime()
            ->since()
            ->icon('heroicon-o-clock')
            ->size('sm');
    }

    private static function makeUpdatedAtEntry(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label('Last Updated')
            ->dateTime()
            ->since()
            ->icon('heroicon-o-arrow-path')
            ->size('sm');
    }

    private static function makeSearchNameEntry(): TextEntry
    {
        return TextEntry::make('search_name')
            ->label('Search Name')
            ->placeholder('—')
            ->icon('heroicon-o-magnifying-glass')
            ->size('sm')
            ->visible(fn ($record) => ! empty($record?->search_name));
    }

    private static function makeAccountIdEntry(): TextEntry
    {
        return TextEntry::make('id')
            ->label('Record ID')
            ->copyable()
            ->icon('heroicon-o-finger-print')
            ->size('sm')
            ->color('gray');
    }

    private static function stateValue(mixed $state): string|int|null
    {
        if ($state instanceof BackedEnum) {
            return $state->value;
        }

        if (is_string($state) || is_int($state)) {
            return $state;
        }

        return null;
    }

    private static function stateLabel(mixed $state): string
    {
        if (is_object($state) && method_exists($state, 'getLabel')) {
            return (string) $state->getLabel();
        }

        if (is_object($state) && method_exists($state, 'label')) {
            return (string) $state->label();
        }

        $value = self::stateValue($state);

        if ($value === null || $value === '') {
            return 'Not Set';
        }

        return Str::headline((string) $value);
    }

    private static function incomeBalanceLabel(mixed $state): string
    {
        if (is_object($state) && method_exists($state, 'label')) {
            return (string) $state->label();
        }

        return match (self::stateValue($state)) {
            IncomeBalanceType::BALANCE_SHEET->value, '0' => 'Balance Sheet',
            IncomeBalanceType::INCOME_STATEMENT->value, '1' => 'Income Statement',
            default => self::stateLabel($state),
        };
    }

    private static function isBalanceSheetState(mixed $state): bool
    {
        return in_array(self::stateValue($state), [IncomeBalanceType::BALANCE_SHEET->value, '0'], true);
    }

    private static function accountStatusSummary(mixed $record): ?string
    {
        if (! $record) {
            return null;
        }

        $statuses = [];

        if ($record->blocked) {
            $statuses[] = 'Blocked';
        }

        if (! $record->allowsDirectPosting()) {
            $statuses[] = 'No Direct Posting';
        }

        if (! $record->isPostingAccount()) {
            $type = self::stateLabel($record->structural_type ?: 'non_posting');
            $statuses[] = "{$type} Account";
        }

        return empty($statuses)
            ? 'Active - Ready for Posting'
            : implode(' • ', $statuses);
    }

    private static function accountStatusColor(mixed $record): string
    {
        return match (true) {
            ! $record || $record->blocked => 'danger',
            ! $record->allowsDirectPosting() => 'warning',
            default => 'success',
        };
    }
}
