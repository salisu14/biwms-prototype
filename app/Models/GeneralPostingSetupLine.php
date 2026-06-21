<?php

// app/Models/GeneralPostingSetupLine.php

namespace App\Models;

use App\Enums\LineType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralPostingSetupLine extends Model
{
    use HasFactory;

    protected $table = 'general_posting_setup_lines';

    protected $fillable = [
        'general_posting_setup_id',
        'line_type',
        'chart_of_account_id',
    ];

    protected $casts = [
        'line_type' => LineType::class,
    ];

    // Relationships
    public function generalPostingSetup(): BelongsTo
    {
        return $this->belongsTo(GeneralPostingSetup::class);
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    // Scope by line type
    public function scopeOfType($query, string $type)
    {
        return $query->where('line_type', $type);
    }
}
