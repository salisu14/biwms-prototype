<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhysicalInventoryJournal extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_batch_name',
        'description',
        'posting_date',
        'document_date',
        'status',
        'location_code',
        'bin_code',
        'reason_code',
        'assigned_user_id',
        'counted_by',
        'counted_at',
        'posted_by',
        'posted_at',
        'sorting_method',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'document_date' => 'date',
        'counted_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PhysicalInventoryLine::class, 'journal_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_code', 'code');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'bin_code', 'bin_code');
    }

    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reason_code', 'code');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'Open');
    }

    public function scopeCounting($query)
    {
        return $query->where('status', 'Counting');
    }

    public function scopeCalculated($query)
    {
        return $query->where('status', 'Calculated');
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'Posted');
    }

    public function calculate(): void
    {
        foreach ($this->lines as $line) {
            $line->update([
                'qty_calculated' => $line->quantity_base - $line->qty_physical_inventory,
                'entry_type' => $line->qty_physical_inventory > $line->quantity_base
                    ? 'Positive Adjmt.'
                    : 'Negative Adjmt.',
            ]);
        }
        $this->update(['status' => 'Calculated']);
    }
}
