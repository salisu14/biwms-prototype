<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionOperationNoteCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOperationNote extends Model
{
    protected $fillable = [
        'production_operation_execution_id',
        'category',
        'body',
        'attachment_path',
        'private',
        'created_by',
    ];

    protected $casts = [
        'category' => ProductionOperationNoteCategory::class,
        'private' => 'boolean',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ProductionOperationExecution::class, 'production_operation_execution_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
