<?php

namespace Bhhaskin\Billing\Tests\Feature;

use Bhhaskin\Billing\Http\Controllers\WebhookController;
use Bhhaskin\Billing\Models\Customer;
use Bhhaskin\Billing\Models\Invoice;
use Bhhaskin\Billing\Models\WebhookEvent;
use Bhhaskin\Billing\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class WebhookIdempotencyTest extends TestCase
{
    protected string $secret = 'whsec_test_secret_for_unit_tests_only';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('billing.stripe.webhook_secret', $this->secret);

        Route::post('/billing/webhook/stripe', [WebhookController::class, 'handle'])
            ->name('billing.webhook.stripe');
    }

    /** @test */
    public function it_records_a_webhook_event_on_first_delivery(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'stripe_id' => 'in_test_record_first',
            'status' => Invoice::STATUS_OPEN,
        ]);

        $payload = $this->buildPayload('evt_record_first', 'invoice.payment_succeeded', [
            'id' => 'in_test_record_first',
        ]);

        $response = $this->postRaw($payload);

        $response->assertOk()->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('billing_webhook_events', [
            'stripe_event_id' => 'evt_record_first',
            'type' => 'invoice.payment_succeeded',
        ]);

        $event = WebhookEvent::where('stripe_event_id', 'evt_record_first')->firstOrFail();
        $this->assertNotNull($event->processed_at, 'processed_at should be set after successful processing');
    }

    /** @test */
    public function it_dedupes_a_duplicate_delivery_with_the_same_event_id(): void
    {
        $customer = Customer::factory()->create();
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'stripe_id' => 'in_test_duplicate',
            'status' => Invoice::STATUS_OPEN,
        ]);

        $payload = $this->buildPayload('evt_duplicate_xyz', 'invoice.payment_succeeded', [
            'id' => 'in_test_duplicate',
        ]);

        $first = $this->postRaw($payload);
        $first->assertOk()->assertJson(['status' => 'success']);

        $second = $this->postRaw($payload);
        $second->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertEquals(1, WebhookEvent::where('stripe_event_id', 'evt_duplicate_xyz')->count());
    }

    /** @test */
    public function it_rejects_a_request_with_an_invalid_signature(): void
    {
        $body = json_encode([
            'id' => 'evt_bad_signature',
            'object' => 'event',
            'type' => 'invoice.payment_succeeded',
            'data' => ['object' => ['id' => 'in_test_bad']],
        ]);

        $response = $this->call(
            'POST',
            '/billing/webhook/stripe',
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => 't=1,v1=deadbeef', 'CONTENT_TYPE' => 'application/json'],
            $body
        );

        $response->assertStatus(400);
        $this->assertDatabaseCount('billing_webhook_events', 0);
    }

    /**
     * Build a JSON payload representing a Stripe Event.
     */
    protected function buildPayload(string $eventId, string $type, array $object): string
    {
        return json_encode([
            'id' => $eventId,
            'object' => 'event',
            'api_version' => '2024-11-20',
            'created' => time(),
            'type' => $type,
            'data' => ['object' => $object],
        ]);
    }

    /**
     * Sign a payload using the configured webhook secret and POST it as raw JSON.
     */
    protected function postRaw(string $payload)
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $this->secret);
        $header = 't=' . $timestamp . ',v1=' . $signature;

        return $this->call(
            'POST',
            '/billing/webhook/stripe',
            [],
            [],
            [],
            [
                'HTTP_Stripe-Signature' => $header,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );
    }
}
