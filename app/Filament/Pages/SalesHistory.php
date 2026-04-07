<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class SalesHistory extends Page
{
    protected string $view = 'filament.pages.sales-history';

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $title = 'Navigate';

    protected static ?string $navigationLabel = 'History';

    protected static string|null|\UnitEnum $navigationGroup = 'Sales';
}
