<?php

namespace Bhhaskin\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
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
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'total' => $this->total,
            'currency' => $this->currency,
            'due_date' => $this->due_date?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            // Relationships
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'refunds' => RefundResource::collection($this->whenLoaded('refunds')),

            // Computed attributes
            'is_draft' => $this->isDraft(),
            'is_open' => $this->isOpen(),
            'is_paid' => $this->isPaid(),
            'is_void' => $this->isVoid(),
            'total_refunded' => $this->when(
                $this->relationLoaded('refunds'),
                fn () => $this->getTotalRefunded()
            ),
            'remaining_refundable' => $this->when(
                $this->relationLoaded('refunds'),
                fn () => $this->getRemainingRefundable()
            ),
            'is_fully_refunded' => $this->when(
                $this->relationLoaded('refunds'),
                fn () => $this->isFullyRefunded()
            ),
            'is_partially_refunded' => $this->when(
                $this->relationLoaded('refunds'),
                fn () => $this->isPartiallyRefunded()
            ),
        ];
    }
}
