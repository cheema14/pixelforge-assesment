<?php

namespace App\Http\Resources;

use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Generation */
class GenerationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'prompt' => $this->prompt,
            'size' => $this->size,
            'quality' => $this->quality,
            'referenceImages' => $this->reference_images,
            'imageUrl' => $this->image_url,
            'imageUrlExpiresAt' => $this->image_url_expires_at?->toIso8601String(),
            'imageExpired' => $this->hasImageUrlExpired(),
            'estimatedChargeNano' => $this->estimated_charge_nano,
            'maxChargeNano' => $this->max_charge_nano,
            'chargedNano' => $this->charged_nano,
            'errorCode' => $this->error_code,
            'errorMessage' => $this->error_message,
            'createdAt' => $this->created_at?->toIso8601String(),
            'startedAt' => $this->started_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
        ];
    }
}
