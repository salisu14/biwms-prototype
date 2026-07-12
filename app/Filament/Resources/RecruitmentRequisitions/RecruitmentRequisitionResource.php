<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentRequisitions;

use App\Filament\Resources\RecruitmentRequisitions\Pages\CreateRecruitmentRequisition;
use App\Filament\Resources\RecruitmentRequisitions\Pages\EditRecruitmentRequisition;
use App\Filament\Resources\RecruitmentRequisitions\Pages\ListRecruitmentRequisitions;
use App\Filament\Resources\RecruitmentRequisitions\Pages\ViewRecruitmentRequisition;
use App\Filament\Resources\RecruitmentRequisitions\Schemas\RecruitmentRequisitionForm;
use App\Filament\Resources\RecruitmentRequisitions\Schemas\RecruitmentRequisitionInfolist;
use App\Filament\Resources\RecruitmentRequisitions\Tables\RecruitmentRequisitionsTable;
use App\Models\RecruitmentRequisition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentRequisitionResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_requisition';
    }

    protected static ?string $model = RecruitmentRequisition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentRequisitionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentRequisitionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentRequisitionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecruitmentRequisitions::route('/'),
            'create' => CreateRecruitmentRequisition::route('/create'),
            'view' => ViewRecruitmentRequisition::route('/{record}'),
            'edit' => EditRecruitmentRequisition::route('/{record}/edit'),
        ];
    }
}
