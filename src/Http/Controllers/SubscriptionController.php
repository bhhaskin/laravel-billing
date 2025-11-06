<?php

namespace Bhhaskin\Billing\Http\Controllers;

use Bhhaskin\Billing\Events\SubscriptionCanceled;
use Bhhaskin\Billing\Events\SubscriptionCreated;
use Bhhaskin\Billing\Events\SubscriptionResumed;
use Bhhaskin\Billing\Http\Requests\ChangePlanRequest;
use Bhhaskin\Billing\Http\Requests\PreviewPlanChangeRequest;
use Bhhaskin\Billing\Http\Resources\SubscriptionResource;
use Bhhaskin\Billing\Models\Discount;
use Bhhaskin\Billing\Models\Plan;
use Bhhaskin\Billing\Models\Subscription;
use Bhhaskin\Billing\Support\BillingAudit;
use Bhhaskin\Billing\Support\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SubscriptionController extends Controller
{
    public function __construct(
        protected StripeService $stripeService
    ) {
    }

    /**
     * List user's subscriptions.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscriptions = $user->subscriptions()
            ->with('items.plan')
            ->latest()
            ->get();

        return response()->json([
            'data' => $subscriptions,
        ]);
    }

    /**
     * Get a specific subscription.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()
            ->where('uuid', $uuid)
            ->with('items.plan')
            ->firstOrFail();

        return response()->json([
            'data' => $subscription,
        ]);
    }

    /**
     * Create a new subscription.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_uuid' => 'required|string|exists:' . config('billing.tables.plans', 'billing_plans') . ',uuid',
            'quantity' => 'integer|min:1',
        ]);

        $user = $request->user();
        $plan = Plan::where('uuid', $validated['plan_uuid'])->firstOrFail();

        // Ensure user has a customer record
        $customer = $user->getOrCreateCustomer();

        // Create subscription
        $subscription = $customer->subscriptions()->create([
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_start' => now(),
            'current_period_end' => $plan->interval === 'yearly' ? now()->addYear() : now()->addMonth(),
        ]);

        $subscription->addItem($plan, $validated['quantity'] ?? 1);

        // Sync to Stripe if configured
        if (config('billing.stripe.secret')) {
            try {
                $this->stripeService->createSubscription($subscription);
            } catch (\Exception $e) {
                // Handle Stripe error
                $subscription->update(['status' => Subscription::STATUS_INCOMPLETE]);
            }
        }

        BillingAudit::recordSubscriptionChange($subscription, 'created');
        event(new SubscriptionCreated($subscription));

        return response()->json([
            'data' => $subscription->load('items.plan'),
        ], 201);
    }

    /**
     * Cancel a subscription.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $immediately = $request->boolean('immediately', false);

        // Get cancellation behavior from the primary plan
        $cancellationBehavior = 'end_of_period';
        foreach ($subscription->items as $item) {
            if ($item->plan->isPlan()) {
                $cancellationBehavior = $item->plan->cancellation_behavior;
                break;
            }
        }

        $cancelImmediately = $immediately || $cancellationBehavior === 'immediate';

        if ($cancelImmediately) {
            $subscription->update([
                'status' => Subscription::STATUS_CANCELED,
                'canceled_at' => now(),
                'ends_at' => now(),
            ]);
        } else {
            $subscription->update([
                'status' => Subscription::STATUS_CANCELED,
                'canceled_at' => now(),
                'ends_at' => $subscription->current_period_end,
            ]);
        }

        // Sync to Stripe if configured
        if (config('billing.stripe.secret') && $subscription->hasStripeId()) {
            try {
                $this->stripeService->cancelSubscription($subscription, $cancelImmediately);
            } catch (\Exception $e) {
                // Log error but don't fail
            }
        }

        BillingAudit::recordSubscriptionChange($subscription, 'canceled', [
            'immediately' => $cancelImmediately,
        ]);
        event(new SubscriptionCanceled($subscription));

        return response()->json([
            'data' => $subscription,
        ]);
    }

    /**
     * Resume a canceled subscription.
     */
    public function resume(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()
            ->where('uuid', $uuid)
            ->firstOrFail();

        if (! $subscription->onGracePeriod()) {
            return response()->json([
                'error' => 'Subscription cannot be resumed',
            ], 422);
        }

        $subscription->update([
            'status' => Subscription::STATUS_ACTIVE,
            'canceled_at' => null,
            'ends_at' => null,
        ]);

        BillingAudit::recordSubscriptionChange($subscription, 'resumed');
        event(new SubscriptionResumed($subscription));

        return response()->json([
            'data' => $subscription,
        ]);
    }

    /**
     * Apply a discount to a subscription.
     */
    public function applyDiscount(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();
        $subscription = $user->subscriptions()
            ->where('uuid', $uuid)
            ->with('items.plan')
            ->firstOrFail();

        // Find and validate discount code
        $discount = Discount::byCode($validated['code'])->active()->first();

        if (! $discount) {
            return response()->json([
                'error' => 'Discount code not found or has expired',
            ], 404);
        }

        try {
            $appliedDiscount = $subscription->applyDiscount($discount);

            // Sync to Stripe if configured
            if (config('billing.stripe.secret') && $subscription->hasStripeId()) {
                try {
                    $this->stripeService->applyDiscountToSubscription($subscription, $discount);
                } catch (\Exception $e) {
                    // Log error but don't fail
                }
            }

            BillingAudit::recordSubscriptionChange($subscription, 'discount_applied', [
                'discount_code' => $discount->code,
                'discount_name' => $discount->name,
            ]);

            return response()->json([
                'message' => 'Discount applied successfully',
                'subscription' => $subscription->load('appliedDiscounts.discount'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove a discount from a subscription.
     */
    public function removeDiscount(Request $request, string $uuid, string $discountUuid): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $discount = Discount::where('uuid', $discountUuid)->firstOrFail();

        if (! $subscription->hasDiscount($discount)) {
            return response()->json([
                'error' => 'Discount is not applied to this subscription',
            ], 422);
        }

        $subscription->removeDiscount($discount);

        // If this was the last discount, remove from Stripe too
        if (config('billing.stripe.secret') && $subscription->hasStripeId() && $subscription->getActiveDiscounts()->isEmpty()) {
            try {
                $this->stripeService->removeDiscountFromSubscription($subscription);
            } catch (\Exception $e) {
                // Log error but don't fail
            }
        }

        BillingAudit::recordSubscriptionChange($subscription, 'discount_removed', [
            'discount_code' => $discount->code,
            'discount_name' => $discount->name,
        ]);

        return response()->json([
            'message' => 'Discount removed successfully',
            'subscription' => $subscription->load('appliedDiscounts.discount'),
        ]);
    }

    /**
     * List active discounts for a subscription.
     */
    public function discounts(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $discounts = $subscription->getActiveDiscounts();

        return response()->json([
            'data' => $discounts,
        ]);
    }

    /**
     * Preview a plan change
     */
    public function previewPlanChange(PreviewPlanChangeRequest $request, string $uuid): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $subscription = $user->subscriptions()
            ->where('uuid', $uuid)
            ->with('items.plan')
            ->firstOrFail();

        $newPlan = Plan::where('uuid', $validated['new_plan_uuid'])->firstOrFail();

        $this->authorize('previewPlanChange', [$subscription, $newPlan]);

        try {
            $preview = $subscription->previewPlanChange($newPlan);

            return response()->json([
                'data' => $preview,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Change subscription plan
     */
    public function changePlan(ChangePlanRequest $request, string $uuid): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $subscription = $user->subscriptions()
            ->where('uuid', $uuid)
            ->with('items.plan')
            ->firstOrFail();

        $newPlan = Plan::where('uuid', $validated['new_plan_uuid'])->firstOrFail();

        $this->authorize('changePlan', [$subscription, $newPlan]);

        try {
            $options = [];

            if (isset($validated['prorate'])) {
                $options['prorate'] = $validated['prorate'];
            }

            if ($validated['schedule'] ?? false) {
                $options['schedule'] = true;
                if (isset($validated['schedule_for'])) {
                    $options['schedule_for'] = new \DateTime($validated['schedule_for']);
                }
            }

            $subscription->changePlan($newPlan, $options);

            BillingAudit::recordSubscriptionChange($subscription, 'plan_changed', [
                'new_plan' => $newPlan->name,
                'new_plan_uuid' => $newPlan->uuid,
                'scheduled' => $options['schedule'] ?? false,
            ]);

            $message = ($options['schedule'] ?? false)
                ? 'Plan change scheduled successfully'
                : 'Plan changed successfully';

            return response()->json([
                'message' => $message,
                'subscription' => new SubscriptionResource($subscription->fresh()->load(['items.plan', 'scheduledPlan'])),
            ]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel scheduled plan change
     */
    public function cancelScheduledPlanChange(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()
            ->where('uuid', $uuid)
            ->firstOrFail();

        if (! $subscription->hasScheduledPlanChange()) {
            return response()->json([
                'error' => 'No scheduled plan change found',
            ], 422);
        }

        $subscription->cancelScheduledPlanChange();

        BillingAudit::recordSubscriptionChange($subscription, 'scheduled_plan_change_canceled');

        return response()->json([
            'message' => 'Scheduled plan change canceled successfully',
            'subscription' => $subscription,
        ]);
    }
}
