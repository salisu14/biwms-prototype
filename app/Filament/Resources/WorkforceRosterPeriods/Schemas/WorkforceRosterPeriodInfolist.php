<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterPeriods\Schemas;

use App\Models\WorkforceRosterPeriod;
use Carbon\Carbon;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkforceRosterPeriodInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            self::makeMainGrid(),
        ]);
    }

    private static function makeMainGrid(): Grid
    {
        return Grid::make(3)->schema([
            self::makeIdentityColumn(),
            self::makeScopeColumn(),
            self::makeTimelineColumn(),
        ]);
    }

    // ==================== COLUMN 1: IDENTITY ====================

    private static function makeIdentityColumn(): Group
    {
        return Group::make([
            self::makeOverviewSection(),
            self::makeStatisticsSection(),
        ]);
    }

    private static function makeOverviewSection(): Section
    {
        return Section::make('Period Overview')
            ->icon('heroicon-o-document-text')
            ->schema([
                Grid::make(2)->schema([
                    self::makeCodeEntry(),
                    self::makeNameEntry(),
                    self::makeStatusEntry(),
                    self::makeDurationEntry(),
                ]),
            ]);
    }

    private static function makeCodeEntry(): TextEntry
    {
        return TextEntry::make('code')
            ->label('Period Code')
            ->weight('bold')
            ->copyable()
            ->copyMessage('Copied!')
            ->icon('heroicon-o-hashtag');
    }

    private static function makeNameEntry(): TextEntry
    {
        return TextEntry::make('name')
            ->label('Period Name')
            ->weight('medium')
            ->columnSpanFull();
    }

    private static function makeStatusEntry(): TextEntry
    {
        return TextEntry::make('status')
            ->label('Current Status')
            ->badge()
            ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucwords($state)))
            ->color(fn (string $state): string => match ($state) {
                WorkforceRosterPeriod::STATUS_DRAFT => 'gray',
                WorkforceRosterPeriod::STATUS_GENERATED => 'info',
                WorkforceRosterPeriod::STATUS_UNDER_REVIEW => 'warning',
                WorkforceRosterPeriod::STATUS_PUBLISHED,
                WorkforceRosterPeriod::STATUS_ACTIVE => 'success',
                WorkforceRosterPeriod::STATUS_CLOSED => 'primary',
                WorkforceRosterPeriod::STATUS_CANCELLED => 'danger',
                WorkforceRosterPeriod::STATUS_REOPENED => 'warning',
                default => 'gray',
            });
    }

    private static function makeDurationEntry(): TextEntry
    {
        return TextEntry::make('duration')
            ->label('Duration')
            ->getStateUsing(function (WorkforceRosterPeriod $record): string {
                $start = Carbon::parse($record->date_from);
                $end = Carbon::parse($record->date_to);
                $days = $start->diffInDays($end) + 1;
                $weeks = round($days / 7, 1);

                return "{$days} days ({$weeks} weeks)";
            })
            ->icon('heroicon-o-calendar-days');
    }

    private static function makeStatisticsSection(): Section
    {
        return Section::make('Assignment Statistics')
            ->icon('heroicon-o-chart-bar')
            ->schema([
                Grid::make(2)->schema([
                    self::makeTotalAssignmentsEntry(),
                    self::makeUniqueEmployeesEntry(),

                    self::makeCompletedAssignmentsEntry(),
                    self::makePendingAssignmentsEntry(),
                ]),
            ]);
    }

    private static function makeTotalAssignmentsEntry(): TextEntry
    {
        return TextEntry::make('total_assignments')
            ->label('Total Assignments')
            ->getStateUsing(fn (WorkforceRosterPeriod $record): int => $record->assignments()->count()
            )
            ->size('lg')
            ->weight('bold')
            ->icon('heroicon-o-users');
    }

    private static function makeUniqueEmployeesEntry(): TextEntry
    {
        return TextEntry::make('unique_employees')
            ->label('Unique Employees')
            ->getStateUsing(fn (WorkforceRosterPeriod $record): int => $record->assignments()->distinct('employee_id')->count('employee_id')
            )
            ->icon('heroicon-o-user-group');
    }

    private static function makeCompletedAssignmentsEntry(): TextEntry
    {
        return TextEntry::make('completed_count')
            ->label('Completed')
            ->getStateUsing(fn (WorkforceRosterPeriod $record): int => $record->assignments()->where('status', 'completed')->count()
            )
            ->color('success')
            ->icon('heroicon-o-check-circle');
    }

    private static function makePendingAssignmentsEntry(): TextEntry
    {
        return TextEntry::make('pending_count')
            ->label('Pending/Accepted')
            ->getStateUsing(fn (WorkforceRosterPeriod $record): int => $record->assignments()->whereIn('status', ['scheduled', 'published', 'accepted'])->count()
            )
            ->color('warning')
            ->icon('heroicon-o-clock');
    }

    // ==================== COLUMN 2: SCOPE ====================

    private static function makeScopeColumn(): Group
    {
        return Group::make([
            self::makeScopeSection(),
            self::makeDatesSection(),
            self::makeNotesSection(),
        ]);
    }

    private static function makeScopeSection(): Section
    {
        return Section::make('Scope Configuration')
            ->icon('heroicon-o-map-pin')
            ->schema([
                Grid::make(1)->schema([
                    self::makeDepartmentEntry(),
                    self::makeWorkCenterEntry(),
                    self::makeLocationEntry(),
                ]),
            ]);
    }

    private static function makeDepartmentEntry(): TextEntry
    {
        return TextEntry::make('department.name')
            ->label('Department')
            ->icon('heroicon-o-building-office-2')
            ->placeholder('Not specified');
    }

    private static function makeWorkCenterEntry(): TextEntry
    {
        return TextEntry::make('workCenter.name')
            ->label('Work Center')
            ->icon('heroicon-o-cog-6-tooth')
            ->placeholder('Not specified');
    }

    private static function makeLocationEntry(): TextEntry
    {
        return TextEntry::make('attendanceLocation.name')
            ->label('Attendance Location')
            ->icon('heroicon-o-map-pin')
            ->placeholder('Not specified')
            ->formatStateUsing(function ($state, WorkforceRosterPeriod $record): string {
                if (! $record->attendanceLocation) {
                    return 'Not specified';
                }

                $location = $record->attendanceLocation;
                $parts = [$location->name];

                if ($location->address) {
                    $parts[] = $location->address;
                }

                return implode(' — ', $parts);
            });
    }

    private static function makeDatesSection(): Section
    {
        return Section::make('Date Range')
            ->icon('heroicon-o-calendar')
            ->schema([
                Grid::make(2)->schema([
                    self::makeDateFromEntry(),
                    self::makeDateToEntry(),
                ]),
            ]);
    }

    private static function makeDateFromEntry(): TextEntry
    {
        return TextEntry::make('date_from')
            ->label('Start Date')
            ->date()
            ->formatStateUsing(function ($state): string {
                return Carbon::parse($state)->format('F j, Y (l)');
            })
            ->icon('heroicon-o-arrow-start-on-rectangle');
    }

    private static function makeDateToEntry(): TextEntry
    {
        return TextEntry::make('date_to')
            ->label('End Date')
            ->date()
            ->formatStateUsing(function ($state): string {
                return Carbon::parse($state)->format('F j, Y (l)');
            })
            ->icon('heroicon-o-arrow-end-on-rectangle');
    }

    private static function makeNotesSection(): Section
    {
        return Section::make('Notes & Instructions')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->collapsed()
            ->schema([
                TextEntry::make('notes')
                    ->placeholder('No notes provided')
                    ->markdown()
                    ->columnSpanFull(),
            ])
            ->visible(fn (WorkforceRosterPeriod $record): bool => ! empty($record->notes));
    }

    // ==================== COLUMN 3: TIMELINE ====================

    private static function makeTimelineColumn(): Group
    {
        return Group::make([
            self::makeWorkflowTimelineSection(),
            self::makeSystemDetailsSection(),
        ]);
    }

    private static function makeWorkflowTimelineSection(): Section
    {
        return Section::make('Workflow Timeline')
            ->icon('heroicon-o-clock')
            ->schema([
                Grid::make(1)->schema([
                    self::makeGeneratedAtEntry(),
                    self::makeSubmittedAtEntry(),
                    self::makePublishedAtEntry(),
                    self::makeClosedAtEntry(),
                    self::makeReopenedAtEntry(),
                ]),
            ]);
    }

    private static function makeGeneratedAtEntry(): TextEntry
    {
        return TextEntry::make('generated_at')
            ->label('Generated')
            ->dateTime('M d, Y g:i A')
            ->since()
            ->placeholder('Not generated yet')
            ->icon('heroicon-o-cog')
            ->visible(fn (WorkforceRosterPeriod $record): bool => $record->generated_at !== null || $record->status === WorkforceRosterPeriod::STATUS_GENERATED
            );
    }

    private static function makeSubmittedAtEntry(): TextEntry
    {
        return TextEntry::make('submitted_at')
            ->label('Submitted for Review')
            ->dateTime('M d, Y g:i A')
            ->since()
            ->placeholder('Not submitted')
            ->icon('heroicon-o-paper-airplane')
            ->visible(fn (WorkforceRosterPeriod $record): bool => $record->submitted_at !== null
            );
    }

    private static function makePublishedAtEntry(): TextEntry
    {
        return TextEntry::make('published_at')
            ->label('Published')
            ->dateTime('M d, Y g:i A')
            ->since()
            ->placeholder('Not published')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->weight('medium')
            ->visible(fn (WorkforceRosterPeriod $record): bool => $record->isPublishedLike() || $record->published_at !== null
            );
    }

    private static function makeClosedAtEntry(): TextEntry
    {
        return TextEntry::make('closed_at')
            ->label('Closed')
            ->dateTime('M d, Y g:i A')
            ->since()
            ->placeholder('Still open')
            ->icon('heroicon-o-lock-closed')
            ->color('warning')
            ->visible(fn (WorkforceRosterPeriod $record): bool => $record->status === WorkforceRosterPeriod::STATUS_CLOSED || $record->closed_at !== null
            );
    }

    private static function makeReopenedAtEntry(): TextEntry
    {
        return TextEntry::make('reopened_at')
            ->label('Reopened')
            ->dateTime('M d, Y g:i A')
            ->since()
            ->placeholder('Never reopened')
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->visible(fn (WorkforceRosterPeriod $record): bool => $record->status === WorkforceRosterPeriod::STATUS_REOPENED || $record->reopened_at !== null
            );
    }

    private static function makeSystemDetailsSection(): Section
    {
        return Section::make('System Details')
            ->icon('heroicon-o-information-circle')
            ->collapsed()
            ->schema([
                Grid::make(2)->schema([
                    self::makeCreatedAtEntry(),
                    self::makeUpdatedAtEntry(),
                    self::makeIdEntry(),
                    self::makeReopenReasonEntry(),
                ]),
            ]);
    }

    private static function makeCreatedAtEntry(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label('Created At')
            ->dateTime('M d, Y g:i A')
            ->since()
            ->icon('heroicon-o-clock')
            ->size('sm');
    }

    private static function makeUpdatedAtEntry(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label('Last Updated')
            ->dateTime('M d, Y g:i A')
            ->since()
            ->icon('heroicon-o-arrow-path')
            ->size('sm');
    }

    private static function makeIdEntry(): TextEntry
    {
        return TextEntry::make('id')
            ->label('Record ID')
            ->copyable()
            ->icon('heroicon-o-fingerprint')
            ->size('sm')
            ->color('gray');
    }

    private static function makeReopenReasonEntry(): TextEntry
    {
        return TextEntry::make('reopen_reason')
            ->label('Reopen Reason')
            ->placeholder('No reason recorded')
            ->markdown()
            ->columnSpanFull()
            ->visible(fn (WorkforceRosterPeriod $record): bool => ! empty($record->reopen_reason));
    }
}
