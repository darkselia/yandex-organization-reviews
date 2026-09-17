<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_url' => $this->source_url,
            'name' => $this->name,
            'rating' => $this->rating === null ? null : (float) $this->rating,
            'ratings_count' => $this->ratings_count,
            'reviews_count' => $this->reviews_count,
            'last_synced_at' => $this->last_synced_at?->toISOString(),
        ];
    }
}
