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
            'status' => $this->status->value,
            'attempt_count' => $this->attempt_count,
            'reviews_fetched' => $this->reviews_fetched,
            'reviews_expected' => $this->reviews_expected,
            'progress_percent' => $progressPercent,
            'error' => $this->error_code === null ? null : [
                'code' => $this->error_code,
                'message' => $this->error_message,
            ],
        ];
    }
}
