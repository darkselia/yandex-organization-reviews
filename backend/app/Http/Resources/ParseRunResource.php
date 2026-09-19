<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParseRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $progressPercent = null;

        if ($this->reviews_expected !== null && $this->reviews_expected > 0) {
            $progressPercent = min(100, (int) round(
                $this->reviews_fetched / $this->reviews_expected * 100,
            ));
        }

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'status' => $this->status->value,
            'attempt_count' => $this->attempt_count,
            'reviews_fetched' => $this->reviews_fetched,
            'reviews_expected' => $this->reviews_expected,
            'progress_percent' => $progressPercent,
            'error' => $this->error_code === null ? null : [
                'code' => $this->publicErrorCode(),
            ],
        ];
    }

    private function publicErrorCode(): string
    {
        return match ($this->error_code) {
            'empty_source_response',
            'invalid_source_data',
            'source_schema_changed' => 'organization_unavailable',
            'source_unavailable',
            'source_blocked',
            'source_rate_limited' => 'source_temporarily_unavailable',
            default => 'processing_failed',
        };
    }
}
