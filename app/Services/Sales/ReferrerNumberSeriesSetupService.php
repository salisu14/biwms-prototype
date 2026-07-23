<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use Illuminate\Support\Facades\Log;

class ReferrerNumberSeriesSetupService
{
    public const CODE = 'REFERRER';

    /**
     * @return array<string, mixed>
     */
    public function ensure(): array
    {
        $series = NumberSeries::query()->firstOrCreate(
            ['code' => self::CODE],
            [
                'description' => 'Referrers',
                'prefix' => 'REF',
                'starting_number' => 1,
                'ending_number' => 999999,
                'current_number' => 0,
                'year' => (int) now()->format('Y'),
                'is_active' => true,
                'allow_manual' => false,
                'module' => 'sales',
            ],
        );

        $line = NumberSeriesLine::query()->firstOrCreate(
            [
                'number_series_id' => $series->id,
                'starting_date' => now()->startOfYear()->toDateString(),
            ],
            [
                'prefix' => 'REF-',
                'suffix' => null,
                'no_of_digits' => 6,
                'starting_no' => 1,
                'ending_no' => null,
                'last_no_used' => 0,
                'increment_by' => 1,
                'blocked' => false,
            ],
        );

        Log::info('Referrer number series provisioning checked', [
            'series_code' => self::CODE,
            'series_status' => $series->wasRecentlyCreated ? 'created' : 'found',
            'line_status' => $line->wasRecentlyCreated ? 'created' : 'found',
            'line_id' => $line->id,
            'last_no_used_preserved' => $line->last_no_used,
        ]);

        return [
            'code' => self::CODE,
            'series_created' => $series->wasRecentlyCreated,
            'line_created' => $line->wasRecentlyCreated,
            'series_id' => $series->id,
            'line_id' => $line->id,
            'last_no_used' => $line->last_no_used,
        ];
    }
}
