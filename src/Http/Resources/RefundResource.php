<?php

namespace Bhhaskin\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
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
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'reason' => $this->reason,
            'description' => $this->description,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            // Relationships
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'credit' => new CustomerCreditResource($this->whenLoaded('credit')),

            // Computed attributes
            'is_pending' => $this->isPending(),
            'is_succeeded' => $this->isSucceeded(),
            'is_failed' => $this->isFailed(),
            'is_canceled' => $this->status === \Bhhaskin\Billing\Models\Refund::STATUS_CANCELED,
        ];
    }
}
