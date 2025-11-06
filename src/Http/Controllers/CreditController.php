<?php

namespace Bhhaskin\Billing\Http\Controllers;

use Bhhaskin\Billing\Models\CustomerCredit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CreditController extends Controller
{
    /**
     * Get customer credit balance
     */
    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();
        $customer = $user->customer;

        if (! $customer) {
            return response()->json([
                'balance' => 0,
                'formatted' => '$0.00',
            ]);
        }

        return response()->json([
            'balance' => $customer->getAvailableCredit(),
            'formatted' => '$' . number_format($customer->getAvailableCredit(), 2),
            'currency' => config('billing.currency', 'usd'),
        ]);
    }

    /**
     * List credit transactions
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $customer = $user->customer;

        if (! $customer) {
            return response()->json(['data' => []]);
        }

        $credits = $customer->credits()
            ->with(['invoice', 'refund'])
            ->latest()
            ->paginate(20);

        return response()->json($credits);
    }

    /**
     * Get a specific credit transaction
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $customer = $user->customer;

        if (! $customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $credit = $customer->credits()
            ->where('uuid', $uuid)
            ->with(['invoice', 'refund'])
            ->firstOrFail();

        return response()->json(['data' => $credit]);
    }

    /**
     * Get credit history summary
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $customer = $user->customer;

        if (! $customer) {
            return response()->json([
                'current_balance' => 0,
                'total_earned' => 0,
                'total_spent' => 0,
                'active_credits' => 0,
                'expired_credits' => 0,
            ]);
        }

        $totalEarned = $customer->credits()->credits()->sum('amount');
        $totalSpent = abs($customer->credits()->debits()->sum('amount'));
        $activeCredits = $customer->credits()->active()->count();
        $expiredCredits = $customer->credits()->expired()->count();

        return response()->json([
            'current_balance' => $customer->getAvailableCredit(),
            'total_earned' => $totalEarned,
            'total_spent' => $totalSpent,
            'active_credits' => $activeCredits,
            'expired_credits' => $expiredCredits,
            'by_type' => [
                'refunds' => $customer->credits()->ofType(CustomerCredit::TYPE_REFUND)->sum('amount'),
                'promotional' => $customer->credits()->ofType(CustomerCredit::TYPE_PROMOTIONAL)->sum('amount'),
                'adjustments' => $customer->credits()->ofType(CustomerCredit::TYPE_MANUAL_ADJUSTMENT)->sum('amount'),
            ],
        ]);
    }
}
