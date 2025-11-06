<?php

namespace Bhhaskin\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerCreditResource extends JsonResource
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
            'type' => $this->type,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'description' => $this->description,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),

            // Relationships
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'refund' => new RefundResource($this->whenLoaded('refund')),

            // Computed attributes
            'is_credit' => $this->isCredit(),
            'is_debit' => $this->isDebit(),
            'is_active' => $this->isActive(),
            'is_expired' => $this->is_expired,
        ];
    }
}
