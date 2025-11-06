<?php

namespace Bhhaskin\Billing\Http\Requests;

use Bhhaskin\Billing\Models\Plan;
use Bhhaskin\Billing\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChangePlanRequest extends FormRequest
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

        $newPlan = Plan::where('uuid', $this->new_plan_uuid)->first();

        if (! $newPlan) {
            return false;
        }

        return $this->user()->can('changePlan', [$subscription, $newPlan]);
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
            'schedule' => [
                'nullable',
                'boolean',
            ],
            'schedule_for' => [
                'nullable',
                'date',
                'after:now',
                'before:' . now()->addYear()->toDateString(),
            ],
            'prorate' => [
                'nullable',
                'boolean',
            ],
            'quantity' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $subscription = Subscription::where('uuid', $this->route('uuid'))->first();
            $newPlan = Plan::where('uuid', $this->new_plan_uuid)->first();

            if (! $subscription || ! $newPlan) {
                return;
            }

            // Validate subscription is active or trialing
            if (! $subscription->isActive() && ! $subscription->isTrialing()) {
                $validator->errors()->add(
                    'subscription',
                    'Only active or trialing subscriptions can change plans.'
                );
            }

            // Validate new plan is different from current plan
            $currentPlan = $subscription->getCurrentPlan();
            if ($currentPlan && $currentPlan->id === $newPlan->id) {
                $validator->errors()->add(
                    'new_plan_uuid',
                    'The new plan is the same as the current plan.'
                );
            }

            // Validate new plan is active
            if (! $newPlan->is_active) {
                $validator->errors()->add(
                    'new_plan_uuid',
                    'The selected plan is not currently available.'
                );
            }

            // Validate schedule_for is provided if schedule is true
            if ($this->input('schedule') && ! $this->has('schedule_for')) {
                $validator->errors()->add(
                    'schedule_for',
                    'A schedule date is required when scheduling a plan change.'
                );
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'new_plan_uuid.required' => 'A new plan is required.',
            'new_plan_uuid.exists' => 'The selected plan does not exist.',
            'schedule_for.after' => 'The scheduled date must be in the future.',
            'schedule_for.before' => 'The scheduled date cannot be more than one year in the future.',
            'quantity.min' => 'Quantity must be at least :min.',
            'quantity.max' => 'Quantity cannot exceed :max.',
        ];
    }
}
