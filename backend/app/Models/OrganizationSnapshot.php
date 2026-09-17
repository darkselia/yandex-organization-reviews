<?php

namespace App\Models;

use Database\Factories\OrganizationSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'rating',
    'ratings_count',
    'reviews_count',
    'captured_at',
])]
class OrganizationSnapshot extends Model
{
    /** @use HasFactory<OrganizationSnapshotFactory> */
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
            'rating' => 'decimal:2',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'captured_at' => 'datetime',
        ];
    }
}
