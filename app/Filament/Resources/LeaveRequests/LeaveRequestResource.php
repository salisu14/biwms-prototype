<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeaveRequests;

use App\Filament\Resources\LeaveRequests\Pages\CreateLeaveRequest;
use App\Filament\Resources\LeaveRequests\Pages\EditLeaveRequest;
use App\Filament\Resources\LeaveRequests\Pages\ListLeaveRequests;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Hr\LeaveRequestService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeaveRequestResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'leave_request';
    }

    protected static ?string $model = LeaveRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Leave Management';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Leave Request')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('employee_id')->options(fn (): array => Employee::query()->orderBy('employee_number')->pluck('full_name', 'id')->all())->searchable()->required(),
                    Select::make('leave_type_id')->options(fn (): array => LeaveType::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())->searchable()->required(),
                    DatePicker::make('start_date')->required(),
                    DatePicker::make('end_date')->required(),
                    Select::make('start_part')->options(['full_day' => 'Full Day', 'morning' => 'Morning', 'afternoon' => 'Afternoon'])->default('full_day')->required(),
                    Select::make('end_part')->options(['full_day' => 'Full Day', 'morning' => 'Morning', 'afternoon' => 'Afternoon'])->default('full_day')->required(),
                    TextInput::make('contact_during_leave')->maxLength(255),
                    FileUpload::make('attachment_path')->disk('local')->directory('leave-attachments')->visibility('private'),
                    Textarea::make('reason')->columnSpanFull(),
                    Textarea::make('handover_notes')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('request_number')->label('Request No.')->searchable()->sortable(),
                TextColumn::make('employee.employee_number')->label('Emp No.')->searchable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(['employee.first_name', 'employee.last_name']),
                TextColumn::make('leaveType.name')->label('Leave Type')->searchable(),
                TextColumn::make('start_date')->date()->sortable(),
                TextColumn::make('end_date')->date()->sortable(),
                TextColumn::make('requested_quantity')->numeric()->label('Qty'),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        LeaveRequest::STATUS_DRAFT => 'Draft',
                        LeaveRequest::STATUS_SUBMITTED => 'Submitted',
                        LeaveRequest::STATUS_MANAGER_APPROVED => 'Manager Approved',
                        LeaveRequest::STATUS_APPROVED => 'Approved',
                        LeaveRequest::STATUS_POSTED => 'Posted',
                        LeaveRequest::STATUS_REJECTED => 'Rejected',
                        LeaveRequest::STATUS_CANCELLED => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('submit')
                        ->icon('heroicon-o-paper-airplane')
                        ->visible(fn (LeaveRequest $record): bool => auth()->user()?->can('submit', $record) === true)
                        ->action(fn (LeaveRequest $record): LeaveRequest => app(LeaveRequestService::class)->submit($record, auth()->user())),
                    Action::make('managerApprove')
                        ->label('Manager Approve')
                        ->icon('heroicon-o-check-circle')
                        ->visible(fn (LeaveRequest $record): bool => $record->status === LeaveRequest::STATUS_SUBMITTED && auth()->user()?->can('approve', $record) === true)
                        ->action(fn (LeaveRequest $record): LeaveRequest => app(LeaveRequestService::class)->managerApprove($record, auth()->user())),
                    Action::make('hrApprove')
                        ->label('HR Approve')
                        ->icon('heroicon-o-shield-check')
                        ->visible(fn (LeaveRequest $record): bool => in_array($record->status, [LeaveRequest::STATUS_SUBMITTED, LeaveRequest::STATUS_MANAGER_APPROVED], true) && auth()->user()?->can('hr.leave_approval.approve') === true)
                        ->action(fn (LeaveRequest $record): LeaveRequest => app(LeaveRequestService::class)->hrApprove($record, auth()->user())),
                    Action::make('reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([Textarea::make('reason')->required()->maxLength(1000)])
                        ->visible(fn (LeaveRequest $record): bool => auth()->user()?->can('reject', $record) === true)
                        ->action(fn (LeaveRequest $record, array $data): LeaveRequest => app(LeaveRequestService::class)->reject($record, auth()->user(), $data['reason'])),
                    SensitiveActionPasswordConfirmation::protect(
                        Action::make('cancel')
                            ->icon('heroicon-o-no-symbol')
                            ->color('warning')
                            ->form([Textarea::make('reason')->maxLength(1000)])
                            ->visible(fn (LeaveRequest $record): bool => auth()->user()?->can('cancel', $record) === true)
                            ->action(fn (LeaveRequest $record, array $data): LeaveRequest => app(LeaveRequestService::class)->cancel($record, auth()->user(), $data['reason'] ?? null))
                    ),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeaveRequests::route('/'),
            'create' => CreateLeaveRequest::route('/create'),
            'edit' => EditLeaveRequest::route('/{record}/edit'),
        ];
    }
}
