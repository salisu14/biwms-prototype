<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentCandidateDocument extends Model
{
    protected $guarded = [];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'verified_at' => 'datetime',
        'is_confidential' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (RecruitmentCandidateDocument $document): void {
            if (str_contains($document->file_path, '..') || str_starts_with($document->file_path, '/')) {
                throw new \RuntimeException('Candidate document paths must be storage-relative private paths.');
            }
        });
    }
}
