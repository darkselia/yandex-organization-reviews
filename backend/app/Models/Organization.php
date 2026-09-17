<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'source',
    'external_id',
    'source_url',
    'normalized_url',
    'name',
    'rating',
    'ratings_count',
    'reviews_count',
    'last_synced_at',
])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<ParseRun, $this> */
    public function parseRuns(): HasMany
    {
        return $this->hasMany(ParseRun::class);
    }

    /** @return HasOne<ParseRun, $this> */
    public function latestParseRun(): HasOne
    {
        return $this->hasOne(ParseRun::class)->latestOfMany();
    }

    /** @return HasMany<OrganizationSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(OrganizationSnapshot::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }
}
