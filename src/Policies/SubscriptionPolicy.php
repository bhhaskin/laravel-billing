<?php

namespace Bhhaskin\Billing\Policies;

use Bhhaskin\Billing\Models\Plan;
use Bhhaskin\Billing\Models\Subscription;
use Illuminate\Contracts\Auth\Authenticatable;

class SubscriptionPolicy
{
    /**
     * Determine if the user can view any subscriptions.
     */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->customer !== null;
    }

    /**
     * Determine if the user can view the subscription.
     */
    public function view(Authenticatable $user, Subscription $subscription): bool
    {
        return $user->customer && $user->customer->id === $subscription->customer_id;
    }

    /**
     * Determine if the user can create subscriptions.
     */
    public function create(Authenticatable $user): bool
    {
        return $user->customer !== null;
    }

    /**
     * Determine if the user can update the subscription.
     */
    public function update(Authenticatable $user, Subscription $subscription): bool
    {
        return $user->customer && $user->customer->id === $subscription->customer_id;
    }

    /**
     * Determine if the user can cancel the subscription.
     */
    public function cancel(Authenticatable $user, Subscription $subscription): bool
    {
        // Can only cancel active or trialing subscriptions
        return $user->customer
            && $user->customer->id === $subscription->customer_id
            && ($subscription->isActive() || $subscription->isTrialing());
    }

    /**
     * Determine if the user can resume the subscription.
     */
    public function resume(Authenticatable $user, Subscription $subscription): bool
    {
        // Can only resume canceled subscriptions that haven't ended
        return $user->customer
            && $user->customer->id === $subscription->customer_id
            && $subscription->isCanceled()
            && ! $subscription->hasEnded();
    }

    /**
     * Determine if the user can change the subscription plan.
     */
    public function changePlan(Authenticatable $user, Subscription $subscription, Plan $newPlan): bool
    {
        // User can change plan if:
        // 1. They own the subscription
        // 2. Subscription is active or trialing
        // 3. New plan is different from current plan
        return $user->customer
            && $user->customer->id === $subscription->customer_id
            && ($subscription->isActive() || $subscription->isTrialing())
            && $subscription->getCurrentPlan()?->id !== $newPlan->id;
    }

    /**
     * Determine if the user can preview plan changes.
     */
    public function previewPlanChange(Authenticatable $user, Subscription $subscription): bool
    {
        return $user->customer
            && $user->customer->id === $subscription->customer_id
            && ($subscription->isActive() || $subscription->isTrialing());
    }

    /**
     * Determine if the user can cancel a scheduled plan change.
     */
    public function cancelScheduledPlanChange(Authenticatable $user, Subscription $subscription): bool
    {
        return $user->customer
            && $user->customer->id === $subscription->customer_id
            && $subscription->hasScheduledPlanChange();
    }

    /**
     * Determine if the user can apply discounts to the subscription.
     */
    public function applyDiscount(Authenticatable $user, Subscription $subscription): bool
    {
        return $user->customer
            && $user->customer->id === $subscription->customer_id
            && ($subscription->isActive() || $subscription->isTrialing());
    }

    /**
     * Determine if the user can remove discounts from the subscription.
     */
    public function removeDiscount(Authenticatable $user, Subscription $subscription): bool
    {
        return $user->customer
            && $user->customer->id === $subscription->customer_id
            && ($subscription->isActive() || $subscription->isTrialing());
    }

    /**
     * Determine if the user can delete the subscription.
     */
    public function delete(Authenticatable $user, Subscription $subscription): bool
    {
        // Alias for cancel
        return $this->cancel($user, $subscription);
    }
}
