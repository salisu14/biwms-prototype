<?php

// app/Models/InventoryAdjustmentJournal.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAdjustmentJournal extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_batch_name',
        'description',
        'posting_date',
        'document_date',
        'status', // Open, Released, Posted
        'reason_code',
        'location_code',
        'assigned_user_id',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'document_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentLine::class, 'journal_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_code', 'code');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reason_code', 'code');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'Open');
    }

    public function scopeReleased($query)
    {
        return $query->where('status', 'Released');
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'Posted');
    }

    public function canPost(): bool
    {
        return $this->status === 'Released' && $this->lines()->count() > 0;
    }
}
