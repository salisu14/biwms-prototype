<?php

namespace App\Filament\Resources\SalesQuotes\RelationManagers;

use App\Filament\Resources\SalesQuotes\SalesQuoteResource;
use App\Services\Sales\SalesQuotePdfService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $relatedResource = SalesQuoteResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version')
            ->columns([
                // 1. Revision Version & Status Combined
                TextColumn::make('version')
                    ->label('Revision')
                    ->formatStateUsing(fn($state) => "v{$state}")
                    ->badge()
                    ->color(fn($record): string => match ($record->status) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn($record): string => match ($record->status) {
                        'approved' => 'heroicon-m-check-badge',
                        'pending' => 'heroicon-m-clock',
                        'rejected' => 'heroicon-m-x-circle',
                        default => 'heroicon-m-document-text',
                    })
                    ->sortable(),

                // 2. Quantity Changes (Visual Diff)
                TextColumn::make('changes')
                    ->label('Quantity Changes')
                    ->html()
                    ->formatStateUsing(fn($state) => $this->formatChangesHtml($state))
                    ->wrap(),

                // 3. Activity Timestamp
                TextColumn::make('created_at')
                    ->label('Modified')
                    ->dateTime('M d, Y')
                    ->description(fn($record) => $record->created_at->format('H:i') . " ({$record->created_at->diffForHumans()})")
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('salesQuote.quote_no')
                    ->formatStateUsing(fn ($state) => $this->safeString($state)),
            ])
            ->filters([
                // Add status filter for long revision histories
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('download_pdf')
                    ->action(function ($record) {
                        return response()->streamDownload(function () use ($record) {
                            echo app(SalesQuotePdfService::class)
                                ->generate($record->salesQuote, $record)
                                ->output();
                        }, "Quote-{$record->salesQuote->quote_no}-v{$record->version}.pdf");
                    }),

                Action::make('restore')
                    ->label('Restore this Version')
                    ->icon('heroicon-s-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Restore Revision')
                    ->modalDescription('Are you sure you want to revert the current quote to these quantities?')
                    ->action(function ($record) {
                        $record->salesQuote->restoreRevision($record);

                        Notification::make()
                            ->title('Revision Restored')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('version', 'desc')
            ->emptyStateHeading('No revisions yet')
            ->emptyStateIcon('heroicon-o-clock');
    }

    /**
     * Formats the changes into a modern UI diff.
     */
    protected function formatChangesHtml($changes): HtmlString|string
    {
        $changesArray = $this->safeDecodeChanges($changes);

        if (empty($changesArray)) {
            return new HtmlString('<span class="text-gray-400 italic text-sm">No quantity changes</span>');
        }

        $lines = collect($changesArray)->map(function ($change) {
            $itemId = $this->safe($change['item_id'] ?? '?');
            $old = (int)($change['old_qty'] ?? 0);
            $new = (int)($change['new_qty'] ?? 0);

            $isIncrease = $new > $old;
            $colorClass = $isIncrease ? 'text-success-600' : 'text-danger-600';
            $icon = $isIncrease ? '↑' : '↓';

            return "
            <div class='flex items-center space-x-2 py-0.5'>
                <span class='text-xs font-mono px-1.5 py-0.5 bg-gray-100 rounded text-gray-600'>
                    ID:{$itemId}
                </span>
                <span class='text-gray-400 line-through text-sm'>{$old}</span>
                <span class='text-gray-300'>→</span>
                <span class='{$colorClass} font-bold text-sm'>{$new}</span>
                <span class='{$colorClass} text-[10px] font-black'>{$icon}</span>
            </div>
        ";
        });

        return new HtmlString("<div class='space-y-1'>" . $lines->implode('') . "</div>");
    }

    protected function safeDecodeChanges($changes): array
    {
        try {
            // Already array (because of cast)
            if (is_array($changes)) {
                return $this->utf8ize($changes);
            }

            // Decode JSON safely
            $decoded = json_decode($changes, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $this->utf8ize($decoded) : [];
        } catch (\Throwable $e) {
            \Log::warning('Invalid JSON in changes field', [
                'changes' => $changes,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    protected function utf8ize(array $data): array
    {
        return array_map(function ($value) {
            if (is_array($value)) {
                return $this->utf8ize($value);
            }

            if (is_string($value)) {
                return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }

            return $value;
        }, $data);
    }

    protected function safe($value): string
    {
        return e(mb_convert_encoding((string)$value, 'UTF-8', 'UTF-8'));
    }

    protected function safeString($value): string
    {
        return mb_convert_encoding((string) $value, 'UTF-8', 'UTF-8');
    }
}
