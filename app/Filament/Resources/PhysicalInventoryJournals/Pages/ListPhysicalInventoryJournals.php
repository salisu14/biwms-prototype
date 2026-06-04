<?php

namespace App\Filament\Resources\PhysicalInventoryJournals\Pages;

use App\Filament\Resources\PhysicalInventoryJournals\PhysicalInventoryJournalResource;
use App\Jobs\PopulatePhysicalInventoryLines;
use App\Models\Location;
use App\Models\PhysicalInventoryJournal;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListPhysicalInventoryJournals extends ListRecords
{
    protected static string $resource = PhysicalInventoryJournalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('calculate_inventory')
                ->label('Calculate Inventory')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->schema([
                    Select::make('location_code')
                        ->relationship('location', 'code')
                        ->required(),
                    Select::make('items_filter')
                        ->options([
                            'all' => 'All Items',
                            'with_stock' => 'Items with Stock',
                            'counting_period' => 'Counting Period Due',
                        ])
                        ->default('all'),
                ])
                ->action(function (array $data) {
                    $journal = DB::transaction(function () use ($data): PhysicalInventoryJournal {
                        $locationName = $this->resolveLocationDisplayName($data['location_code']);

                        $journal = PhysicalInventoryJournal::create([
                            'journal_batch_name' => $this->makeJournalBatchName($data['location_code']),
                            'description' => sprintf('Inventory calculation for %s', $locationName),
                            'posting_date' => now()->toDateString(),
                            'document_date' => now()->toDateString(),
                            'status' => 'Open',
                            'location_code' => $data['location_code'],
                            'sorting_method' => 'Item',
                            'assigned_user_id' => auth()->id(),
                        ]);

                        PopulatePhysicalInventoryLines::dispatchSync($journal->id, $data);

                        return $journal;
                    });

                    return redirect()->to(
                        PhysicalInventoryJournalResource::getUrl('view', ['record' => $journal])
                    );
                }),
        ];
    }

    protected function makeJournalBatchName(string $locationCode): string
    {
        $locationName = $this->resolveLocationDisplayName($locationCode);

        return sprintf(
            'PI-%s-%s-%s',
            Str::upper(Str::slug($locationName)),
            now()->format('YmdHis'),
            Str::upper(Str::random(4))
        );
    }

    protected function resolveLocationDisplayName(string $locationCode): string
    {
        return Location::query()
            ->where('code', $locationCode)
            ->value('name') ?? $locationCode;
    }
}
