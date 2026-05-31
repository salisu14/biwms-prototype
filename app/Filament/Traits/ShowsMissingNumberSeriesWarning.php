<?php

declare(strict_types=1);

namespace App\Filament\Traits;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

trait ShowsMissingNumberSeriesWarning
{
    protected function warnIfMissingNumberSeries(array $seriesCodes, string $documentLabel): void
    {
        $user = auth()->user();
        if (! $user || ! ($user->hasRole('super_admin') || $user->hasRole('admin'))) {
            return;
        }

        $today = now()->toDateString();

        $hasUsableSeries = DB::table('number_series as ns')
            ->join('number_series_lines as nsl', 'nsl.number_series_id', '=', 'ns.id')
            ->whereIn('ns.code', $seriesCodes)
            ->where('ns.is_active', true)
            ->where('nsl.blocked', false)
            ->whereDate('nsl.starting_date', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('nsl.ending_date')
                    ->orWhereDate('nsl.ending_date', '>=', $today);
            })
            ->exists();

        if ($hasUsableSeries) {
            return;
        }

        Notification::make()
            ->title('Number Series Setup Missing')
            ->body("No usable number series was found for {$documentLabel}. Configure one of: ".implode(', ', $seriesCodes).'.')
            ->warning()
            ->persistent()
            ->send();
    }
}
