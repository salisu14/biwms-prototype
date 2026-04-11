<?php

// app/Models/NumberSeries.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code',
    'description',
    'prefix',
    'starting_number',
    'ending_number',
    'current_number',
    'year',
    'is_active',
    'allow_manual',
    'module', // purchase, sales, inventory, etc.
])]
class NumberSeries extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $table = 'number_series';

    protected $casts = [
        'starting_number' => 'integer',
        'ending_number' => 'integer',
        'current_number' => 'integer',
        'year' => 'integer',
        'is_active' => 'boolean',
        'allow_manual' => 'boolean',
    ];

    /**
     * Get next number in series
     */
    public function getNextNumber(): ?string
    {
        if (! $this->is_active) {
            return null;
        }

        $next = $this->current_number + 1;

        if ($this->ending_number && $next > $this->ending_number) {
            return null; // Series exhausted
        }

        // Format: YEAR-P-00001 or use prefix
        $year = $this->year ?? date('Y');
        $prefix = $this->prefix ?? 'P';

        return sprintf('%d-%s-%05d', $year, $prefix, $next);
    }

    /**
     * Increment and save current number
     */
    public function incrementNumber(): void
    {
        $this->increment('current_number');
    }

    /**
     * Generate and reserve next number
     */
    public function generateNumber(): ?string
    {
        $number = $this->getNextNumber();

        if ($number) {
            $this->incrementNumber();
        }

        return $number;
    }

    /**
     * Check if year needs reset
     */
    public function checkYearReset(): void
    {
        $currentYear = (int) date('Y');

        if ($this->year !== $currentYear) {
            $this->year = $currentYear;
            $this->current_number = $this->starting_number - 1;
            $this->save();
        }
    }
}
