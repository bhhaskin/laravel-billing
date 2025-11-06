<?php

namespace Bhhaskin\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'value' => $this->value,
            'currency' => $this->currency,
            'applies_to' => $this->applies_to,
            'applicable_plan_ids' => $this->applicable_plan_ids,
            'duration' => $this->duration,
            'duration_in_months' => $this->duration_in_months,
            'max_redemptions' => $this->max_redemptions,
            'redemptions_count' => $this->redemptions_count,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            // Computed attributes
            'is_valid' => $this->isValid(),
        ];
    }
}
