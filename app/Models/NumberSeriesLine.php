<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumberSeriesLine extends Model
{
    /**
     * The attributes that are mass assignable.
     * * Based on your specific schema:
     * - prefix/suffix: Fixed strings attached to the number
     * - last_no_used: The raw integer representing the sequence
     * - no_of_digits: The length of the numeric part (e.g., 6 for 000001)
     */
    protected $fillable = [
        'number_series_id',
        'starting_date',
        'starting_no',
        'ending_no',
        'increment_by',
        'last_no_used',
        'no_of_digits',
        'prefix',
        'suffix',
        'blocked',
    ];

    /**
     * Attribute Casting
     * Ensuring numeric operations don't fail due to string types.
     */
    protected $casts = [
        'starting_date' => 'date',
        'starting_no' => 'integer',
        'ending_no' => 'integer',
        'last_no_used' => 'integer',
        'increment_by' => 'integer',
        'no_of_digits' => 'integer',
        'blocked' => 'boolean',
    ];

    /**
     * Relationship to the Header (NoSeries)
     */
    public function noSeries(): BelongsTo
    {
        return $this->belongsTo(NumberSeries::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(NumberSeries::class, 'number_series_id');
    }

    /**
     * Generate the Next Formatted Number
     * * This logic is now much simpler because we use the prefix/suffix columns
     * instead of regex-based string extraction.
     * * Example: prefix "INV-", suffix "/24", last_no 5, digits 4
     * Result: "INV-0006/24"
     */
    public function getNextFullNumber(): ?string
    {
        if ($this->blocked) {
            return null;
        }

        // 1. Calculate next numeric value
        $nextValue = ($this->last_no_used ?? ($this->starting_no - $this->increment_by)) + $this->increment_by;

        // 2. Check if we have exceeded the limit
        if ($this->ending_no > 0 && $nextValue > $this->ending_no) {
            return null;
        }

        // 3. Pad the number based on the schema's no_of_digits
        $paddedNumber = str_pad(
            (string) $nextValue,
            $this->no_of_digits,
            '0',
            STR_PAD_LEFT
        );

        // 4. Stitch parts together: [PREFIX][PADDED_NUMBER][SUFFIX]
        return ($this->prefix ?? '').$paddedNumber.($this->suffix ?? '');
    }

    /**
     * Update the last used number in the database
     */
    public function incrementLastNoUsed(): bool
    {
        $nextValue = ($this->last_no_used ?? ($this->starting_no - $this->increment_by)) + $this->increment_by;

        return $this->update([
            'last_no_used' => $nextValue,
        ]);
    }

    /**
     * Scope to find the active line for a specific date
     */
    public function scopeActiveOn($query, $date)
    {
        return $query->where('blocked', false)
            ->where('starting_date', '<=', $date)
            ->orderBy('starting_date', 'desc');
    }
}
