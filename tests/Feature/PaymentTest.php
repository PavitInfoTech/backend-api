<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected SubscriptionPlan $plan;
    protected string $authToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'hashedpassword123',
        ]);

        $this->authToken = $this->user->createToken('test-token')->plainTextToken;

        $this->plan = SubscriptionPlan::create([
            'name' => 'Pro Plan',
            'slug' => 'pro',
            'description' => 'Professional tier',
            'price' => 19.99,
            'currency' => 'USD',
            'interval' => 'monthly',
            'trial_days' => 14,
            'features' => ['feature1', 'feature2'],
            'is_active' => true,
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->authToken];
    }

    protected function validPaymentMethod(): array
    {
        return [
            'card_number' => '4242424242424242',
            'expiry_month' => '12',
            'expiry_year' => '28',
            'cvv' => '123',
            'card_holder' => 'Test User',
        ];
    }

    // ===== Subscription Plans Tests =====

    public function test_can_list_subscription_plans(): void
    {
        $response = $this->getJson('/api/subscription-plans');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['id', 'name', 'slug', 'description', 'price', 'currency', 'interval', 'features'],
                ],
            ]);
    }

    public function test_can_get_single_subscription_plan(): void
    {
        $response = $this->getJson('/api/subscription-plans/pro');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'pro')
            ->assertJsonPath('data.price', '19.99');
    }

    public function test_returns_404_for_nonexistent_plan(): void
    {
        $response = $this->getJson('/api/subscription-plans/nonexistent');

        $response->assertNotFound();
    }

    // ===== Subscribe Tests =====

    public function test_user_can_subscribe_to_plan(): void
    {
        $response = $this->postJson('/api/subscriptions', [
            'plan_slug' => 'pro',
            'payment_method' => $this->validPaymentMethod(),
        ], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => ['payment'],
            ]);

        // Payment should be recorded with plan_name
        $this->assertDatabaseHas('payments', [
            'user_id' => $this->user->id,
            'plan_name' => 'pro',
            'status' => 'completed',
        ]);

        // User should have their current_plan updated
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'current_plan' => 'pro',
        ]);
    }

    public function test_can_subscribe_multiple_times_and_updates_user_plan(): void
    {
        // First subscription
        $first = $this->postJson('/api/subscriptions', [
            'plan_slug' => 'pro',
            'payment_method' => $this->validPaymentMethod(),
        ], $this->authHeaders());

        $first->assertCreated();

        // Second attempt should succeed as payments are allowed; user plan will be overwritten
        $second = $this->postJson('/api/subscriptions', [
            'plan_slug' => 'pro',
            'payment_method' => $this->validPaymentMethod(),
        ], $this->authHeaders());

        $second->assertCreated();

        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'current_plan' => 'pro',
        ]);
    }

    public function test_subscribe_requires_authentication(): void
    {
        $response = $this->postJson('/api/subscriptions', [
            'plan_slug' => 'pro',
            'payment_method' => $this->validPaymentMethod(),
        ]);

        $response->assertUnauthorized();
    }

    public function test_subscribe_validates_payment_method(): void
    {
        $response = $this->postJson('/api/subscriptions', [
            'plan_slug' => 'pro',
            'payment_method' => [
                'card_number' => '123', // invalid
            ],
        ], $this->authHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method.expiry_month', 'payment_method.cvv']);
    }

    public function test_subscribe_with_declined_card(): void
    {
        $response = $this->postJson('/api/subscriptions', [
            'plan_slug' => 'pro',
            'payment_method' => [
                'card_number' => '4000000000000002', // Test card for decline
                'expiry_month' => '12',
                'expiry_year' => '28',
                'cvv' => '123',
                'card_holder' => 'Test User',
            ],
        ], $this->authHeaders());

        $response->assertStatus(400)
            ->assertJson(['message' => 'Card declined']);
    }

    // subscription lifecycle tests removed — purchases record plan_name and set user.current_plan

    // ===== One-Time Payment Tests =====

    public function test_can_process_one_time_payment(): void
    {
        $response = $this->postJson('/api/payments/process', [
            'amount' => 50.00,
            'description' => 'One-time purchase',
            'payment_method' => $this->validPaymentMethod(),
        ], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.type', 'one-time');

        $this->assertDatabaseHas('payments', [
            'user_id' => $this->user->id,
            'amount' => 50.00,
            'type' => 'one-time',
        ]);
    }

    public function test_payment_validates_minimum_amount(): void
    {
        $response = $this->postJson('/api/payments/process', [
            'amount' => 0.10, // Below minimum
            'payment_method' => $this->validPaymentMethod(),
        ], $this->authHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    // ===== Payment History Tests =====

    public function test_can_list_payment_history(): void
    {
        Payment::create([
            'user_id' => $this->user->id,
            'transaction_id' => 'TXN_TEST123',
            'gateway' => 'sandbox',
            'amount' => 19.99,
            'currency' => 'USD',
            'status' => 'completed',
            'type' => 'subscription',
            'paid_at' => now(),
        ]);

        $response = $this->getJson('/api/payments', $this->authHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'transaction_id', 'amount', 'status'],
                    ],
                ],
            ]);
    }

    public function test_can_verify_payment(): void
    {
        $payment = Payment::create([
            'user_id' => $this->user->id,
            'transaction_id' => 'TXN_VERIFY123',
            'gateway' => 'sandbox',
            'amount' => 19.99,
            'currency' => 'USD',
            'status' => 'completed',
            'type' => 'subscription',
            'paid_at' => now(),
        ]);

        $response = $this->getJson('/api/payments/TXN_VERIFY123', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.payment.transaction_id', 'TXN_VERIFY123');
    }

    // ===== Refund Tests =====

    public function test_can_request_refund(): void
    {
        $payment = Payment::create([
            'user_id' => $this->user->id,
            'transaction_id' => 'TXN_REFUND123',
            'gateway' => 'sandbox',
            'amount' => 19.99,
            'currency' => 'USD',
            'status' => 'completed',
            'type' => 'subscription',
            'paid_at' => now(),
        ]);

        $response = $this->postJson('/api/payments/refund/TXN_REFUND123', [
            'reason' => 'Changed my mind',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.status', 'refunded');
    }

    public function test_cannot_refund_already_refunded_payment(): void
    {
        Payment::create([
            'user_id' => $this->user->id,
            'transaction_id' => 'TXN_ALREADYREFUNDED',
            'gateway' => 'sandbox',
            'amount' => 19.99,
            'currency' => 'USD',
            'status' => 'refunded',
            'type' => 'subscription',
            'paid_at' => now(),
        ]);

        $response = $this->postJson('/api/payments/refund/TXN_ALREADYREFUNDED', [], $this->authHeaders());

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Payment has already been refunded');
    }

    // ===== Webhook Tests =====

    public function test_webhook_handles_payment_completed(): void
    {
        $payment = Payment::create([
            'user_id' => $this->user->id,
            'transaction_id' => 'TXN_WEBHOOK123',
            'gateway' => 'sandbox',
            'amount' => 19.99,
            'currency' => 'USD',
            'status' => 'pending',
            'type' => 'subscription',
        ]);

        $response = $this->postJson('/api/payments/webhook', [
            'event_type' => 'payment.completed',
            'transaction_id' => 'TXN_WEBHOOK123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.received', true);

        $this->assertDatabaseHas('payments', [
            'transaction_id' => 'TXN_WEBHOOK123',
            'status' => 'completed',
        ]);
    }

    public function test_last_plan_returns_null_when_no_plan_purchased(): void
    {
        $response = $this->getJson('/api/payments/last-plan', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.plan', null)
            ->assertJsonPath('data.payment', null);
    }

    public function test_last_plan_returns_most_recent_plan_and_payment(): void
    {
        // Create a previous plan payment
        Payment::create([
            'user_id' => $this->user->id,
            'transaction_id' => 'TXN_PREV',
            'gateway' => 'sandbox',
            'amount' => 9.99,
            'currency' => 'USD',
            'status' => 'completed',
            'type' => 'subscription',
            'plan_name' => 'free',
            'paid_at' => now()->subDays(10),
        ]);

        // Create a more recent plan payment
        $latest = Payment::create([
            'user_id' => $this->user->id,
            'transaction_id' => 'TXN_LATEST',
            'gateway' => 'sandbox',
            'amount' => 19.99,
            'currency' => 'USD',
            'status' => 'completed',
            'type' => 'subscription',
            'plan_name' => 'pro',
            'paid_at' => now(),
        ]);

        $response = $this->getJson('/api/payments/last-plan', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.plan.slug', 'pro')
            ->assertJsonPath('data.payment.transaction_id', 'TXN_LATEST');
    }

    public function test_user_can_revert_plan_and_record_audit(): void
    {
        // Set an initial plan
        $this->user->update(['current_plan' => 'pro']);

        // Ensure alternate plan exists
        SubscriptionPlan::create([
            'name' => 'Free Plan',
            'slug' => 'free',
            'description' => 'Free tier',
            'price' => 0.00,
            'currency' => 'USD',
            'interval' => 'one-time',
            'trial_days' => 0,
            'features' => [],
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/payments/revert-plan', [
            'to_plan' => 'free',
            'reason' => 'Downgrading to free account',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.user.current_plan', 'free')
            ->assertJsonPath('data.payment.type', 'revert')
            ->assertJsonPath('data.payment.plan_name', 'free');

        $this->assertDatabaseHas('payments', [
            'user_id' => $this->user->id,
            'type' => 'revert',
            'plan_name' => 'free',
        ]);
    }

    public function test_revert_plan_validates_plan_exists(): void
    {
        $response = $this->postJson('/api/payments/revert-plan', [
            'to_plan' => 'does-not-exist',
        ], $this->authHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['to_plan']);
    }

    public function test_webhook_validates_required_fields(): void
    {
        $response = $this->postJson('/api/payments/webhook', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['event_type', 'transaction_id']);
    }

    // ===== Sandbox Test Cards =====

    public function test_sandbox_test_card_expired(): void
    {
        $response = $this->postJson('/api/subscriptions', [
            'plan_slug' => 'pro',
            'payment_method' => [
                'card_number' => '4000000000000069',
                'expiry_month' => '12',
                'expiry_year' => '28',
                'cvv' => '123',
                'card_holder' => 'Test User',
            ],
        ], $this->authHeaders());

        $response->assertStatus(400)
            ->assertJson(['message' => 'Card expired']);
    }

    public function test_sandbox_test_card_incorrect_cvv(): void
    {
        $response = $this->postJson('/api/subscriptions', [
            'plan_slug' => 'pro',
            'payment_method' => [
                'card_number' => '4000000000000127',
                'expiry_month' => '12',
                'expiry_year' => '28',
                'cvv' => '123',
                'card_holder' => 'Test User',
            ],
        ], $this->authHeaders());

        $response->assertStatus(400)
            ->assertJson(['message' => 'Incorrect CVV']);
    }
}
