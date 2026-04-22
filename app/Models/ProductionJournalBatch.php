<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JournalBatchStatus;
use App\Models\Manufacturing\ProductionOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionJournalBatch extends Model
{
    protected $table = 'production_journal_batches';

    protected $fillable = [
        'template_id',
        'name',
        'description',
        'status',
        'reason_code',
        'assigned_user_id',
        'production_order_id',
        'dimension_filter',
        'auto_post_on_release',
    ];

    protected $casts = [
        'status' => JournalBatchStatus::class,
        'dimension_filter' => 'array',
        'auto_post_on_release' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProductionJournalTemplate::class, 'template_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProductionJournalLine::class, 'batch_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }
}
