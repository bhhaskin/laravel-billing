<?php

namespace Bhhaskin\Billing\Http\Controllers;

use Bhhaskin\Billing\Models\Discount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DiscountController extends Controller
{
    /**
     * Validate a discount code.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'plan_uuid' => 'nullable|string|exists:billing_plans,uuid',
        ]);

        $discount = Discount::byCode($request->code)->active()->first();

        if (! $discount) {
            return response()->json([
                'valid' => false,
                'message' => 'Discount code not found or has expired',
            ], 404);
        }

        // If plan_uuid provided, check if discount applies to that plan
        if ($request->plan_uuid) {
            $plan = \Bhhaskin\Billing\Models\Plan::where('uuid', $request->plan_uuid)->firstOrFail();

            if (! $discount->canApplyToPlan($plan)) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Discount code cannot be applied to this plan',
                ], 422);
            }
        }

        return response()->json([
            'valid' => true,
            'discount' => [
                'uuid' => $discount->uuid,
                'code' => $discount->code,
                'name' => $discount->name,
                'description' => $discount->description,
                'type' => $discount->type,
                'value' => $discount->value,
                'currency' => $discount->currency,
                'duration' => $discount->duration,
                'duration_in_months' => $discount->duration_in_months,
                'applies_to' => $discount->applies_to,
            ],
        ]);
    }

    /**
     * Get discount details by code.
     *
     * @param  string  $code
     * @return JsonResponse
     */
    public function show(string $code): JsonResponse
    {
        $discount = Discount::byCode($code)->active()->firstOrFail();

        return response()->json([
            'uuid' => $discount->uuid,
            'code' => $discount->code,
            'name' => $discount->name,
            'description' => $discount->description,
            'type' => $discount->type,
            'value' => $discount->value,
            'currency' => $discount->currency,
            'duration' => $discount->duration,
            'duration_in_months' => $discount->duration_in_months,
            'applies_to' => $discount->applies_to,
            'max_redemptions' => $discount->max_redemptions,
            'redemptions_count' => $discount->redemptions_count,
            'expires_at' => $discount->expires_at?->toIso8601String(),
        ]);
    }
}
