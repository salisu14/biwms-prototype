<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalTemplateEntry extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'approval_template_id',
        'sequence_no',
        'approver_type',
        'approver_id',
        'approver_role',
        'hierarchy_levels',
        'dimension_code',
        'allow_delegation',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sequence_no' => 'integer',
        'hierarchy_levels' => 'integer',
        'allow_delegation' => 'boolean',
    ];

    /**
     * Get the template that owns this entry.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ApprovalTemplate::class, 'approval_template_id');
    }

    /**
     * Get the specific user assigned as an approver (if type is 'user').
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * Helper to check if the entry is of a specific type.
     */
    public function isUserType(): bool
    {
        return $this->approver_type === 'user';
    }

    public function isRoleType(): bool
    {
        return $this->approver_type === 'role';
    }

    public function isHierarchyType(): bool
    {
        return $this->approver_type === 'hierarchy';
    }

    public function isDimensionType(): bool
    {
        return $this->approver_type === 'dimension';
    }
}
