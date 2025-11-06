<?php

namespace Bhhaskin\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppliedDiscountResource extends JsonResource
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
            'applied_at' => $this->applied_at->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'total_uses' => $this->total_uses,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            // Relationships
            'discount' => new DiscountResource($this->whenLoaded('discount')),

            // Computed attributes
            'is_active' => $this->isActive(),
        ];
    }
}
