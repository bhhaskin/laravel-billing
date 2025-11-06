<?php

namespace Bhhaskin\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type,
            'price' => $this->price,
            'interval' => $this->interval,
            'interval_count' => $this->interval_count,
            'trial_period_days' => $this->trial_period_days,
            'features' => $this->features,
            'limits' => $this->limits,
            'is_active' => $this->is_active,
            'requires_plan' => $this->requires_plan,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
