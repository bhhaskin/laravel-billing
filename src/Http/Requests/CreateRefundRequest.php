<?php

namespace Bhhaskin\Billing\Http\Requests;

use Bhhaskin\Billing\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateRefundRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $invoice = Invoice::where('uuid', $this->invoice_uuid)->first();

        if (! $invoice) {
            return false;
        }

        return $this->user()->can('create', [static::class, $invoice]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'invoice_uuid' => [
                'required',
                'string',
                'exists:' . config('billing.tables.invoices', 'billing_invoices') . ',uuid',
            ],
            'amount' => [
                'nullable',
                'numeric',
                'min:0.01',
                'max:999999.99',
                'regex:/^\d+(\.\d{1,2})?$/', // Max 2 decimal places
            ],
            'reason' => [
                'nullable',
                'string',
                'in:duplicate,fraudulent,requested_by_customer,other',
            ],
            'description' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $invoice = Invoice::where('uuid', $this->invoice_uuid)->first();

            if (! $invoice) {
                return;
            }

            // Validate invoice is paid
            if (! $invoice->isPaid()) {
                $validator->errors()->add('invoice_uuid', 'Only paid invoices can be refunded.');
                return;
            }

            // Validate invoice is not fully refunded
            if ($invoice->isFullyRefunded()) {
                $validator->errors()->add('invoice_uuid', 'This invoice has already been fully refunded.');
                return;
            }

            // Validate refund amount
            $amount = $this->input('amount') ?? $invoice->total;
            $remaining = $invoice->getRemainingRefundable();

            if ($amount > $remaining) {
                $validator->errors()->add(
                    'amount',
                    "Refund amount cannot exceed remaining refundable amount of {$remaining}."
                );
            }

            // Check for recent duplicate refund requests (idempotency)
            $recentRefund = $invoice->refunds()
                ->where('amount', $amount)
                ->where('status', \Bhhaskin\Billing\Models\Refund::STATUS_PENDING)
                ->where('created_at', '>', now()->subMinutes(5))
                ->first();

            if ($recentRefund) {
                $validator->errors()->add(
                    'invoice_uuid',
                    'A similar refund request was recently submitted. Please wait before trying again.'
                );
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize description
        if ($this->has('description')) {
            $this->merge([
                'description' => strip_tags($this->description),
            ]);
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'invoice_uuid.required' => 'An invoice is required for refund.',
            'invoice_uuid.exists' => 'The selected invoice does not exist.',
            'amount.min' => 'Refund amount must be at least :min.',
            'amount.max' => 'Refund amount cannot exceed :max.',
            'amount.regex' => 'Refund amount must have at most 2 decimal places.',
            'reason.in' => 'Invalid refund reason provided.',
        ];
    }
}
