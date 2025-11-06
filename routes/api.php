<?php

use Bhhaskin\Billing\Http\Controllers\CreditController;
use Bhhaskin\Billing\Http\Controllers\DiscountController;
use Bhhaskin\Billing\Http\Controllers\InvoiceController;
use Bhhaskin\Billing\Http\Controllers\PlanController;
use Bhhaskin\Billing\Http\Controllers\RefundController;
use Bhhaskin\Billing\Http\Controllers\SubscriptionController;
use Bhhaskin\Billing\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Billing API Routes
|--------------------------------------------------------------------------
|
| These routes are not automatically registered. Consumer applications
| should include them in their routes/api.php file:
|
| Route::middleware('auth:sanctum')->group(function () {
|     require __DIR__.'/../vendor/bhhaskin/laravel-billing/routes/api.php';
| });
|
*/

// Public routes with rate limiting
Route::prefix('billing')->middleware('throttle:60,1')->group(function () {
    // Plans
    Route::get('/plans', [PlanController::class, 'index'])->name('billing.plans.index');
    Route::get('/plans/{uuid}', [PlanController::class, 'show'])->name('billing.plans.show');
});

// Protected routes (require authentication middleware in consumer app)
Route::prefix('billing')->middleware(['auth:sanctum'])->group(function () {
    // Read-only routes with moderate rate limiting (60 req/min)
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('billing.subscriptions.index');
        Route::get('/subscriptions/{uuid}', [SubscriptionController::class, 'show'])->name('billing.subscriptions.show');
        Route::get('/subscriptions/{uuid}/discounts', [SubscriptionController::class, 'discounts'])->name('billing.subscriptions.discounts');
        Route::get('/credits', [CreditController::class, 'index'])->name('billing.credits.index');
        Route::get('/credits/balance', [CreditController::class, 'balance'])->name('billing.credits.balance');
        Route::get('/credits/summary', [CreditController::class, 'summary'])->name('billing.credits.summary');
        Route::get('/credits/{uuid}', [CreditController::class, 'show'])->name('billing.credits.show');
        Route::get('/refunds', [RefundController::class, 'index'])->name('billing.refunds.index');
        Route::get('/refunds/{uuid}', [RefundController::class, 'show'])->name('billing.refunds.show');
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('billing.invoices.index');
        Route::get('/invoices/{uuid}', [InvoiceController::class, 'show'])->name('billing.invoices.show');
        Route::get('/discounts/{code}', [DiscountController::class, 'show'])->name('billing.discounts.show');
        Route::post('/discounts/validate', [DiscountController::class, 'validate'])->name('billing.discounts.validate');
        Route::post('/subscriptions/{uuid}/preview-plan-change', [SubscriptionController::class, 'previewPlanChange'])->name('billing.subscriptions.preview-plan-change');
    });

    // Standard write operations with tighter rate limiting (30 req/min)
    Route::middleware('throttle:30,1')->group(function () {
        Route::post('/subscriptions', [SubscriptionController::class, 'store'])->name('billing.subscriptions.store');
        Route::delete('/subscriptions/{uuid}', [SubscriptionController::class, 'destroy'])->name('billing.subscriptions.destroy');
        Route::post('/subscriptions/{uuid}/resume', [SubscriptionController::class, 'resume'])->name('billing.subscriptions.resume');
        Route::post('/subscriptions/{uuid}/discounts', [SubscriptionController::class, 'applyDiscount'])->name('billing.subscriptions.discounts.apply');
        Route::delete('/subscriptions/{uuid}/discounts/{discountUuid}', [SubscriptionController::class, 'removeDiscount'])->name('billing.subscriptions.discounts.remove');
        Route::delete('/subscriptions/{uuid}/scheduled-plan-change', [SubscriptionController::class, 'cancelScheduledPlanChange'])->name('billing.subscriptions.cancel-scheduled-plan-change');
    });

    // Financial operations with strict rate limiting (10 req/min)
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/subscriptions/{uuid}/change-plan', [SubscriptionController::class, 'changePlan'])->name('billing.subscriptions.change-plan');
        Route::post('/refunds', [RefundController::class, 'store'])->name('billing.refunds.store');
        Route::delete('/refunds/{uuid}', [RefundController::class, 'destroy'])->name('billing.refunds.destroy');
    });
});

// Webhook routes (no authentication)
Route::post('/billing/webhook/stripe', [WebhookController::class, 'handle'])->name('billing.webhook.stripe');
