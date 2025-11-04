<?php

namespace Bhhaskin\Billing\Http\Controllers;

use Bhhaskin\Billing\Events\PaymentFailed;
use Bhhaskin\Billing\Events\PaymentSucceeded;
use Bhhaskin\Billing\Models\Invoice;
use Bhhaskin\Billing\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class WebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhook.
     */
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('billing.stripe.webhook_secret');

        if (! $secret) {
            return response()->json(['error' => 'Webhook secret not configured'], 500);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'invoice.payment_succeeded':
                $this->handleInvoicePaymentSucceeded($event->data->object);
                break;

            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($event->data->object);
                break;

            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event->data->object);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;

            case 'payment_method.attached':
                $this->handlePaymentMethodAttached($event->data->object);
                break;

            default:
                // Unhandled event type
                break;
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle successful invoice payment.
     */
    protected function handleInvoicePaymentSucceeded($stripeInvoice): void
    {
        $invoice = Invoice::where('stripe_id', $stripeInvoice->id)->first();

        if ($invoice) {
            $invoice->markAsPaid();
            event(new PaymentSucceeded($invoice));
        }
    }

    /**
     * Handle failed invoice payment.
     */
    protected function handleInvoicePaymentFailed($stripeInvoice): void
    {
        $subscriptionId = $stripeInvoice->subscription ?? null;

        if ($subscriptionId) {
            $subscription = Subscription::where('stripe_id', $subscriptionId)->first();

            if ($subscription) {
                $subscription->increment('failed_payment_count');
                $subscription->update([
                    'status' => Subscription::STATUS_PAST_DUE,
                    'last_failed_payment_at' => now(),
                ]);

                event(new PaymentFailed($subscription, $stripeInvoice->last_finalization_error->message ?? null));
            }
        }
    }

    /**
     * Handle subscription update.
     */
    protected function handleSubscriptionUpdated($stripeSubscription): void
    {
        $subscription = Subscription::where('stripe_id', $stripeSubscription->id)->first();

        if ($subscription) {
            $subscription->update([
                'stripe_status' => $stripeSubscription->status,
                'current_period_start' => $stripeSubscription->current_period_start
                    ? date('Y-m-d H:i:s', $stripeSubscription->current_period_start)
                    : null,
                'current_period_end' => $stripeSubscription->current_period_end
                    ? date('Y-m-d H:i:s', $stripeSubscription->current_period_end)
                    : null,
            ]);
        }
    }

    /**
     * Handle subscription deletion.
     */
    protected function handleSubscriptionDeleted($stripeSubscription): void
    {
        $subscription = Subscription::where('stripe_id', $stripeSubscription->id)->first();

        if ($subscription) {
            $subscription->update([
                'status' => Subscription::STATUS_CANCELED,
                'ends_at' => now(),
            ]);
        }
    }

    /**
     * Handle payment method attachment.
     */
    protected function handlePaymentMethodAttached($stripePaymentMethod): void
    {
        // This can be used to sync payment methods to the database
        // Implementation depends on requirements
    }
}
