<?php

namespace App\Models;

use App\Enums\ParseRunStatus;
use Database\Factories\ParseRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'source_url',
    'normalized_url',
    'source_external_id',
    'status',
    'attempt_count',
    'reviews_expected',
    'reviews_fetched',
    'started_at',
    'finished_at',
    'error_code',
    'error_message',
    'diagnostics',
])]
class ParseRun extends Model
{
    /** @use HasFactory<ParseRunFactory> */
    use HasFactory;

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ParseRunStatus::class,
            'attempt_count' => 'integer',
            'reviews_expected' => 'integer',
            'reviews_fetched' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'diagnostics' => 'array',
        ];
    }
}
