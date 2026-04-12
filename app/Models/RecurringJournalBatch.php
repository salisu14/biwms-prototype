<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JournalBatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringJournalBatch extends Model
{
    use HasFactory;

    protected $table = 'recurring_journal_batches';

    protected $fillable = [
        'template_id',
        'name',
        'description',
        'status',
        'reason_code',
        'assigned_user_id',
        'current_processing_date',
    ];

    protected $casts = [
        'status' => JournalBatchStatus::class,
        'current_processing_date' => 'date',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(RecurringJournalTemplate::class, 'template_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RecurringJournalLine::class, 'batch_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
