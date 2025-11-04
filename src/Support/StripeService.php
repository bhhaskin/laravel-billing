<?php

namespace Bhhaskin\Billing\Support;

use Bhhaskin\Billing\Models\Customer;
use Bhhaskin\Billing\Models\Plan;
use Bhhaskin\Billing\Models\Subscription;
use Stripe\Customer as StripeCustomer;
use Stripe\PaymentMethod as StripePaymentMethod;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use Stripe\Subscription as StripeSubscription;

class StripeService
{
    /**
     * Create or update a customer in Stripe.
     */
    public function syncCustomer(Customer $customer): StripeCustomer
    {
        if ($customer->hasStripeId()) {
            return StripeCustomer::update($customer->stripe_id, [
                'email' => $customer->email,
                'name' => $customer->name,
                'metadata' => $customer->metadata ?? [],
            ]);
        }

        $stripeCustomer = StripeCustomer::create([
            'email' => $customer->email,
            'name' => $customer->name,
            'metadata' => array_merge([
                'customer_id' => $customer->id,
                'customer_uuid' => $customer->uuid,
            ], $customer->metadata ?? []),
        ]);

        $customer->update(['stripe_id' => $stripeCustomer->id]);

        return $stripeCustomer;
    }

    /**
     * Create or update a plan (product and price) in Stripe.
     */
    public function syncPlan(Plan $plan): array
    {
        // Create or update product
        if ($plan->stripe_product_id) {
            $product = StripeProduct::update($plan->stripe_product_id, [
                'name' => $plan->name,
                'description' => $plan->description,
                'metadata' => [
                    'plan_id' => $plan->id,
                    'plan_uuid' => $plan->uuid,
                ],
            ]);
        } else {
            $product = StripeProduct::create([
                'name' => $plan->name,
                'description' => $plan->description,
                'metadata' => [
                    'plan_id' => $plan->id,
                    'plan_uuid' => $plan->uuid,
                ],
            ]);
        }

        // Create or update price
        // Note: Stripe prices are immutable, so we can't update them
        // We need to create a new one if anything changes
        if (! $plan->stripe_price_id) {
            $price = StripePrice::create([
                'product' => $product->id,
                'unit_amount' => (int) ($plan->price * 100), // Convert to cents
                'currency' => config('billing.currency', 'usd'),
                'recurring' => [
                    'interval' => $plan->interval === 'yearly' ? 'year' : 'month',
                    'interval_count' => $plan->interval_count,
                ],
                'metadata' => [
                    'plan_id' => $plan->id,
                    'plan_uuid' => $plan->uuid,
                ],
            ]);

            $plan->update([
                'stripe_product_id' => $product->id,
                'stripe_price_id' => $price->id,
            ]);
        }

        return [
            'product' => $product,
            'price' => $plan->stripe_price_id ? StripePrice::retrieve($plan->stripe_price_id) : null,
        ];
    }

    /**
     * Create a subscription in Stripe.
     */
    public function createSubscription(Subscription $subscription): StripeSubscription
    {
        $customer = $subscription->customer;

        if (! $customer->hasStripeId()) {
            $this->syncCustomer($customer);
        }

        $items = [];
        foreach ($subscription->items as $item) {
            $plan = $item->plan;

            if (! $plan->stripe_price_id) {
                $this->syncPlan($plan);
                $plan->refresh();
            }

            $items[] = [
                'price' => $plan->stripe_price_id,
                'quantity' => $item->quantity,
            ];
        }

        $params = [
            'customer' => $customer->stripe_id,
            'items' => $items,
            'metadata' => [
                'subscription_id' => $subscription->id,
                'subscription_uuid' => $subscription->uuid,
            ],
        ];

        if ($subscription->trial_ends_at) {
            $params['trial_end'] = $subscription->trial_ends_at->timestamp;
        }

        $stripeSubscription = StripeSubscription::create($params);

        $subscription->update([
            'stripe_id' => $stripeSubscription->id,
            'stripe_status' => $stripeSubscription->status,
        ]);

        return $stripeSubscription;
    }

    /**
     * Cancel a subscription in Stripe.
     */
    public function cancelSubscription(Subscription $subscription, bool $immediately = false): StripeSubscription
    {
        if (! $subscription->hasStripeId()) {
            throw new \RuntimeException('Subscription does not have a Stripe ID');
        }

        if ($immediately) {
            return StripeSubscription::update($subscription->stripe_id, [
                'cancel_at_period_end' => false,
            ])->cancel();
        }

        return StripeSubscription::update($subscription->stripe_id, [
            'cancel_at_period_end' => true,
        ]);
    }

    /**
     * Attach a payment method to a customer.
     */
    public function attachPaymentMethod(Customer $customer, string $paymentMethodId): StripePaymentMethod
    {
        if (! $customer->hasStripeId()) {
            $this->syncCustomer($customer);
        }

        $paymentMethod = StripePaymentMethod::retrieve($paymentMethodId);
        $paymentMethod->attach(['customer' => $customer->stripe_id]);

        return $paymentMethod;
    }

    /**
     * Set the default payment method for a customer.
     */
    public function setDefaultPaymentMethod(Customer $customer, string $paymentMethodId): StripeCustomer
    {
        if (! $customer->hasStripeId()) {
            throw new \RuntimeException('Customer does not have a Stripe ID');
        }

        return StripeCustomer::update($customer->stripe_id, [
            'invoice_settings' => [
                'default_payment_method' => $paymentMethodId,
            ],
        ]);
    }
}
