<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class OpeningInventory extends Model
{
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_POSTED = 'POSTED';

    public const STATUS_CANCELLED = 'CANCELLED';

    private static bool $allowStatusTransition = false;

    protected $fillable = [
        'business_id',
        'document_number',
        'posting_date',
        'status',
        'source',
        'description',
        'posted_at',
        'posted_by',
        'created_by',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'posted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            if (
                $document->isDirty('status')
                && in_array($document->status, [self::STATUS_POSTED, self::STATUS_CANCELLED], true)
                && ! self::$allowStatusTransition
            ) {
                throw new RuntimeException("Opening inventory {$document->document_number} status can only be changed through the posting service.");
            }

            if (
                in_array($document->getOriginal('status'), [self::STATUS_POSTED, self::STATUS_CANCELLED], true)
                && $document->isDirty()
            ) {
                throw new RuntimeException("Finalized opening inventory {$document->document_number} is immutable.");
            }
        });

        static::deleting(function (self $document): void {
            if ($document->status === self::STATUS_POSTED) {
                throw new RuntimeException("Posted opening inventory {$document->document_number} cannot be deleted.");
            }

            if ($document->status === self::STATUS_CANCELLED) {
                throw new RuntimeException("Cancelled opening inventory {$document->document_number} cannot be deleted.");
            }

            if (
                $document->lines()->whereNotNull('item_ledger_entry_id')->exists()
                || $document->itemLedgerEntries()->exists()
            ) {
                throw new RuntimeException("Opening inventory {$document->document_number} has ledger records and cannot be deleted.");
            }
        });
    }

    public static function allowServiceStatusTransition(callable $callback): mixed
    {
        $wasAllowed = self::$allowStatusTransition;
        self::$allowStatusTransition = true;

        try {
            return $callback();
        } finally {
            self::$allowStatusTransition = $wasAllowed;
        }
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OpeningInventoryLine::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function itemLedgerEntries(): HasMany
    {
        return $this->hasMany(ItemLedgerEntry::class, 'source_id')
            ->where('source_type', self::class);
    }
}
