<?php

use Bhhaskin\Billing\Http\Controllers\InvoiceController;
use Bhhaskin\Billing\Http\Controllers\PlanController;
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

// Public routes
Route::prefix('billing')->group(function () {
    // Plans
    Route::get('/plans', [PlanController::class, 'index'])->name('billing.plans.index');
    Route::get('/plans/{uuid}', [PlanController::class, 'show'])->name('billing.plans.show');
});

// Protected routes (require authentication middleware in consumer app)
Route::prefix('billing')->middleware(['auth:sanctum'])->group(function () {
    // Subscriptions
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('billing.subscriptions.index');
    Route::post('/subscriptions', [SubscriptionController::class, 'store'])->name('billing.subscriptions.store');
    Route::get('/subscriptions/{uuid}', [SubscriptionController::class, 'show'])->name('billing.subscriptions.show');
    Route::delete('/subscriptions/{uuid}', [SubscriptionController::class, 'destroy'])->name('billing.subscriptions.destroy');
    Route::post('/subscriptions/{uuid}/resume', [SubscriptionController::class, 'resume'])->name('billing.subscriptions.resume');

    // Invoices
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('billing.invoices.index');
    Route::get('/invoices/{uuid}', [InvoiceController::class, 'show'])->name('billing.invoices.show');
});

// Webhook routes (no authentication)
Route::post('/billing/webhook/stripe', [WebhookController::class, 'handle'])->name('billing.webhook.stripe');
