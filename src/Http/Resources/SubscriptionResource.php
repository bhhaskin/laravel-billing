<?php

namespace Bhhaskin\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
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
            'status' => $this->status,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'plan_changed_at' => $this->plan_changed_at?->toIso8601String(),
            'plan_change_scheduled_for' => $this->plan_change_scheduled_for?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            // Relationships
            'items' => SubscriptionItemResource::collection($this->whenLoaded('items')),
            'current_plan' => new PlanResource($this->whenLoaded('items', function () {
                return $this->getCurrentPlan();
            })),
            'previous_plan' => new PlanResource($this->whenLoaded('previousPlan')),
            'scheduled_plan' => new PlanResource($this->whenLoaded('scheduledPlan')),
            'discounts' => AppliedDiscountResource::collection($this->whenLoaded('appliedDiscounts')),

            // Computed attributes
            'is_active' => $this->isActive(),
            'is_trialing' => $this->isTrialing(),
            'is_past_due' => $this->isPastDue(),
            'is_canceled' => $this->isCanceled(),
            'is_suspended' => $this->isSuspended(),
            'has_ended' => $this->hasEnded(),
            'on_grace_period' => $this->onGracePeriod(),
            'has_scheduled_plan_change' => $this->hasScheduledPlanChange(),
        ];
    }
}
