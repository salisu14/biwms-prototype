<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionQualityCheckAttachment extends Model
{
    protected $fillable = [
        'production_quality_check_id',
        'disk',
        'path',
        'original_filename',
        'stored_filename',
        'mime_type',
        'size',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function qualityCheck(): BelongsTo
    {
        return $this->belongsTo(ProductionQualityCheck::class, 'production_quality_check_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
