<?php

namespace App\Http\Resources;

use App\Models\CreditTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CreditTransaction */
class CreditTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'typeLabel' => $this->type->label(),
            'amountNano' => $this->amount_nano,
            'description' => $this->description,
            'generationId' => $this->generation_id,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
