<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemJournalBatch extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'template_id',
        'name',
        'description',
        'assigned_user_id',
        'status',
        'location_id',
        'default_entry_type',
        'dimension_filter',
        'reason_code',
        'copy_item_dimensions',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'dimension_filter' => 'array',
        'copy_item_dimensions' => 'boolean',
    ];

    // --- Relationships ---

    /**
     * The template that defines the behavior and type of this journal.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ItemJournalTemplate::class, 'template_id');
    }

    /**
     * The user assigned to manage this specific batch.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * The default location for lines created within this batch.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reason_code', 'code');
    }

    /**
     * The journal lines associated with this batch.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ItemJournalLine::class, 'journal_batch_id');
    }

    // --- Scopes ---

    /**
     * Scope a query to only include open batches.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope a query to only include batches for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('assigned_user_id', $userId);
    }

    // --- Helpers ---

    /**
     * Determine if the batch is ready for posting.
     */
    public function isReleased(): bool
    {
        return $this->status === 'released';
    }

    /**
     * Check if the batch has already been posted.
     */
    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }
}
