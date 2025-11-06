<?php

namespace Bhhaskin\Billing\Support;

use Bhhaskin\Billing\Models\Customer;
use Bhhaskin\Billing\Models\Discount;
use Bhhaskin\Billing\Models\Plan;
use Bhhaskin\Billing\Models\Refund;
use Bhhaskin\Billing\Models\Subscription;
use Stripe\Coupon as StripeCoupon;
use Stripe\Customer as StripeCustomer;
use Stripe\PaymentMethod as StripePaymentMethod;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use Stripe\PromotionCode as StripePromotionCode;
use Stripe\Refund as StripeRefund;
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

    /**
     * Create or update a discount (coupon) in Stripe.
     */
    public function syncDiscount(Discount $discount): StripeCoupon
    {
        $params = [
            'name' => $discount->name,
            'metadata' => [
                'discount_id' => $discount->id,
                'discount_uuid' => $discount->uuid,
            ],
        ];

        // Set discount value
        if ($discount->type === 'percentage') {
            $params['percent_off'] = $discount->value;
        } else {
            $params['amount_off'] = (int) ($discount->value * 100); // Convert to cents
            $params['currency'] = $discount->currency ?? config('billing.currency', 'usd');
        }

        // Set duration
        if ($discount->duration === 'once') {
            $params['duration'] = 'once';
        } elseif ($discount->duration === 'forever') {
            $params['duration'] = 'forever';
        } elseif ($discount->duration === 'repeating' && $discount->duration_in_months) {
            $params['duration'] = 'repeating';
            $params['duration_in_months'] = $discount->duration_in_months;
        }

        // Set redemption limit
        if ($discount->max_redemptions) {
            $params['max_redemptions'] = $discount->max_redemptions;
        }

        // Set validity period
        if ($discount->expires_at) {
            $params['redeem_by'] = $discount->expires_at->timestamp;
        }

        // Stripe coupons can't be updated, only created
        if ($discount->stripe_coupon_id) {
            try {
                return StripeCoupon::retrieve($discount->stripe_coupon_id);
            } catch (\Exception $e) {
                // Coupon doesn't exist, create a new one
            }
        }

        $coupon = StripeCoupon::create($params);

        $discount->update(['stripe_coupon_id' => $coupon->id]);

        // If this discount has a code, create a promotion code
        if ($discount->code && ! $discount->stripe_promotion_code_id) {
            $this->syncPromotionCode($discount, $coupon->id);
        }

        return $coupon;
    }

    /**
     * Create a promotion code for a discount in Stripe.
     */
    public function syncPromotionCode(Discount $discount, ?string $couponId = null): StripePromotionCode
    {
        if (! $couponId) {
            if (! $discount->stripe_coupon_id) {
                $this->syncDiscount($discount);
                $discount->refresh();
            }
            $couponId = $discount->stripe_coupon_id;
        }

        $params = [
            'coupon' => $couponId,
            'code' => $discount->code,
            'metadata' => [
                'discount_id' => $discount->id,
                'discount_uuid' => $discount->uuid,
            ],
        ];

        if ($discount->max_redemptions) {
            $params['max_redemptions'] = $discount->max_redemptions;
        }

        if ($discount->expires_at) {
            $params['expires_at'] = $discount->expires_at->timestamp;
        }

        if ($discount->starts_at) {
            // Stripe promotion codes don't have a start date, but we can set it as active/inactive
            $params['active'] = $discount->starts_at->isPast();
        }

        if ($discount->stripe_promotion_code_id) {
            try {
                return StripePromotionCode::retrieve($discount->stripe_promotion_code_id);
            } catch (\Exception $e) {
                // Promotion code doesn't exist, create a new one
            }
        }

        $promotionCode = StripePromotionCode::create($params);

        $discount->update(['stripe_promotion_code_id' => $promotionCode->id]);

        return $promotionCode;
    }

    /**
     * Apply a discount to a Stripe subscription.
     */
    public function applyDiscountToSubscription(Subscription $subscription, Discount $discount): StripeSubscription
    {
        if (! $subscription->hasStripeId()) {
            throw new \RuntimeException('Subscription does not have a Stripe ID');
        }

        if (! $discount->stripe_coupon_id) {
            $this->syncDiscount($discount);
            $discount->refresh();
        }

        return StripeSubscription::update($subscription->stripe_id, [
            'coupon' => $discount->stripe_coupon_id,
        ]);
    }

    /**
     * Remove discount from a Stripe subscription.
     */
    public function removeDiscountFromSubscription(Subscription $subscription): StripeSubscription
    {
        if (! $subscription->hasStripeId()) {
            throw new \RuntimeException('Subscription does not have a Stripe ID');
        }

        $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_id);
        $stripeSubscription->deleteDiscount();

        return $stripeSubscription;
    }

    /**
     * Create a refund in Stripe.
     */
    public function createRefund(Refund $refund): StripeRefund
    {
        if ($refund->hasStripeId()) {
            throw new \RuntimeException('Refund already has a Stripe ID');
        }

        $params = [
            'amount' => (int) ($refund->amount * 100), // Convert to cents
            'metadata' => [
                'refund_id' => $refund->id,
                'refund_uuid' => $refund->uuid,
            ],
        ];

        // Add reason if provided
        if ($refund->reason) {
            $params['reason'] = $refund->reason;
        }

        // Find the Stripe charge/payment intent
        if ($refund->invoice && $refund->invoice->stripe_id) {
            // Get the charge from the invoice
            $stripeInvoice = \Stripe\Invoice::retrieve($refund->invoice->stripe_id);
            if ($stripeInvoice->charge) {
                $params['charge'] = $stripeInvoice->charge;
            }
        }

        try {
            $stripeRefund = StripeRefund::create($params);

            $refund->update([
                'stripe_refund_id' => $stripeRefund->id,
                'status' => Refund::STATUS_SUCCEEDED,
                'processed_at' => now(),
            ]);

            return $stripeRefund;
        } catch (\Exception $e) {
            $refund->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Retrieve refund from Stripe.
     */
    public function retrieveRefund(string $stripeRefundId): StripeRefund
    {
        return StripeRefund::retrieve($stripeRefundId);
    }

    /**
     * Cancel a refund in Stripe.
     */
    public function cancelRefund(Refund $refund): StripeRefund
    {
        if (! $refund->hasStripeId()) {
            throw new \RuntimeException('Refund does not have a Stripe ID');
        }

        $stripeRefund = $this->retrieveRefund($refund->stripe_refund_id);
        $canceledRefund = $stripeRefund->cancel();

        $refund->cancel();

        return $canceledRefund;
    }
}
