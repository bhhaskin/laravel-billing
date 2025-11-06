<?php

namespace Bhhaskin\Billing\Http\Requests;

use Bhhaskin\Billing\Models\Plan;
use Bhhaskin\Billing\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;

class PreviewPlanChangeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $subscription = Subscription::where('uuid', $this->route('uuid'))->first();

        if (! $subscription) {
            return false;
        }

        return $this->user()->can('previewPlanChange', $subscription);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'new_plan_uuid' => [
                'required',
                'string',
                'exists:' . config('billing.tables.plans', 'billing_plans') . ',uuid',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'new_plan_uuid.required' => 'A new plan is required for preview.',
            'new_plan_uuid.exists' => 'The selected plan does not exist.',
        ];
    }
}
