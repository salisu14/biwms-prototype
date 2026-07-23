<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use Illuminate\Support\Facades\Log;

class ProductionOrderNumberSeriesSetupService
{
    public const CODE = 'PROD-ORDER';

    /**
     * @return array<string, mixed>
     */
    public function ensure(): array
    {
        $series = NumberSeries::query()->firstOrCreate(
            ['code' => self::CODE],
            [
                'description' => 'Production Orders',
                'prefix' => 'PROD',
                'starting_number' => 1,
                'ending_number' => 999999,
                'current_number' => 0,
                'year' => (int) now()->format('Y'),
                'is_active' => true,
                'allow_manual' => false,
                'module' => 'factory',
            ],
        );

        $seriesWasCreated = $series->wasRecentlyCreated;

        $line = NumberSeriesLine::query()->firstOrCreate(
            [
                'number_series_id' => $series->id,
                'starting_date' => now()->startOfYear()->toDateString(),
            ],
            [
                'prefix' => 'PROD-',
                'suffix' => null,
                'no_of_digits' => 5,
                'starting_no' => 1,
                'ending_no' => null,
                'last_no_used' => 0,
                'increment_by' => 1,
                'blocked' => false,
            ],
        );

        $lineWasCreated = $line->wasRecentlyCreated;

        Log::info('Production order number series provisioning checked', [
            'series_code' => self::CODE,
            'series_status' => $seriesWasCreated ? 'created' : 'found',
            'line_status' => $lineWasCreated ? 'created' : 'found',
            'line_id' => $line->id,
            'last_no_used_preserved' => $line->last_no_used,
        ]);

        return [
            'code' => self::CODE,
            'series_created' => $seriesWasCreated,
            'line_created' => $lineWasCreated,
            'series_id' => $series->id,
            'line_id' => $line->id,
            'last_no_used' => $line->last_no_used,
        ];
    }
}
