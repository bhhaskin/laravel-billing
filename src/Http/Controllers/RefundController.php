<?php

namespace Bhhaskin\Billing\Http\Controllers;

use Bhhaskin\Billing\Http\Requests\CreateRefundRequest;
use Bhhaskin\Billing\Http\Resources\RefundResource;
use Bhhaskin\Billing\Models\Invoice;
use Bhhaskin\Billing\Models\Refund;
use Bhhaskin\Billing\Support\BillingAudit;
use Bhhaskin\Billing\Support\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RefundController extends Controller
{
    public function __construct(
        protected StripeService $stripeService
    ) {
    }

    /**
     * List user's refunds
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Refund::class);

        $user = $request->user();
        $customer = $user->customer;

        if (! $customer) {
            return response()->json(['data' => []]);
        }

        $refunds = $customer->refunds()
            ->with('invoice')
            ->latest()
            ->paginate(20);

        return RefundResource::collection($refunds)->response();
    }

    /**
     * Get a specific refund
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $customer = $user->customer;

        if (! $customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $refund = $customer->refunds()
            ->where('uuid', $uuid)
            ->with(['invoice', 'credit'])
            ->firstOrFail();

        $this->authorize('view', $refund);

        return (new RefundResource($refund))->response();
    }

    /**
     * Create a refund request
     */
    public function store(CreateRefundRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $customer = $user->getOrCreateCustomer();

        // Find invoice
        $invoice = Invoice::where('uuid', $validated['invoice_uuid'])
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $this->authorize('create', [Refund::class, $invoice]);

        try {
            // Create refund
            $refund = $invoice->refund(
                $validated['amount'] ?? null,
                $validated['reason'] ?? Refund::REASON_REQUESTED_BY_CUSTOMER,
                $validated['description'] ?? null
            );

            // Process with Stripe if configured
            if (config('billing.stripe.secret') && $invoice->stripe_id) {
                try {
                    $this->stripeService->createRefund($refund);
                } catch (\Exception $e) {
                    // Log error but don't fail - refund is created locally
                }
            } else {
                // Mark as succeeded if no Stripe
                $refund->markAsSucceeded();
            }

            BillingAudit::recordInvoiceAction($invoice, 'refunded', [
                'refund_uuid' => $refund->uuid,
                'amount' => $refund->amount,
            ]);

            return response()->json([
                'message' => 'Refund request created successfully',
                'data' => new RefundResource($refund->load('invoice')),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel a pending refund
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $customer = $user->customer;

        if (! $customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $refund = $customer->refunds()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('cancel', $refund);

        if (! $refund->isPending()) {
            return response()->json([
                'error' => 'Can only cancel pending refunds',
            ], 422);
        }

        try {
            // Cancel in Stripe if applicable
            if (config('billing.stripe.secret') && $refund->hasStripeId()) {
                $this->stripeService->cancelRefund($refund);
            } else {
                $refund->cancel();
            }

            BillingAudit::recordInvoiceAction($refund->invoice, 'refund_canceled', [
                'refund_uuid' => $refund->uuid,
            ]);

            return response()->json([
                'message' => 'Refund canceled successfully',
                'data' => new RefundResource($refund),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
