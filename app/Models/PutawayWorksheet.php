<?php

namespace App\Models;

use App\Services\NumberSeriesService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model for Put-away Worksheets
 */
class PutawayWorksheet extends Model
{
    protected static function booted(): void
    {
        static::creating(function (PutawayWorksheet $worksheet): void {
            if (empty($worksheet->worksheet_number)) {
                $worksheet->worksheet_number = self::generateWorksheetNumber();
            }
        });
    }

    protected $fillable = [
        'worksheet_number',
        'location_id',
        'user_id',
        'status',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PutawayWorksheetLine::class);
    }

    public static function generateWorksheetNumber(): string
    {
        $seriesService = app(NumberSeriesService::class);

        foreach (['PUTAWAY', 'WH-PUTAWAY'] as $seriesCode) {
            $nextNumber = $seriesService->tryGetNextNo($seriesCode);

            if (! empty($nextNumber)) {
                return $nextNumber;
            }
        }

        $year = date('Y');
        $sequence = static::whereYear('created_at', $year)->count() + 1;

        return sprintf('PUT-%d-%06d', $year, $sequence);
    }
}
