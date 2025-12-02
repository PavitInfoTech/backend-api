<?php

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Services\SandboxPaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentController extends ApiController
{
    protected SandboxPaymentGateway $gateway;

    public function __construct(SandboxPaymentGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * GET /api/subscription-plans
     * List all available subscription plans.
     */
    public function listPlans(): JsonResponse
    {
        $plans = SubscriptionPlan::active()
            ->orderBy('price')
            ->get();

        return $this->success($plans, 'Subscription plans retrieved');
    }

    /**
     * GET /api/subscription-plans/{slug}
     * Get a single subscription plan by slug.
     */
    public function showPlan(string $slug): JsonResponse
    {
        $plan = SubscriptionPlan::where('slug', $slug)->first();

        if (!$plan) {
            return $this->error('Subscription plan not found', 404);
        }

        return $this->success($plan);
    }

    /**
     * POST /api/subscriptions
     * Subscribe to a plan.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_slug' => 'required|string|exists:subscription_plans,slug',
            'payment_method' => 'required|array',
            'payment_method.card_number' => 'required|string|min:13|max:19',
            'payment_method.expiry_month' => 'required|string|size:2',
            'payment_method.expiry_year' => 'required|string|size:2',
            'payment_method.cvv' => 'required|string|min:3|max:4',
            'payment_method.card_holder' => 'required|string|max:255',
            'billing_address' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $user = $request->user();
        $plan = SubscriptionPlan::where('slug', $request->plan_slug)->first();

        // subscription lifecycle removed — payments attach plan name to user

        DB::beginTransaction();
        try {
            // Process payment through sandbox gateway
            $paymentResult = $this->gateway->processPayment([
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'card_number' => $request->input('payment_method.card_number'),
                'expiry_month' => $request->input('payment_method.expiry_month'),
                'expiry_year' => $request->input('payment_method.expiry_year'),
                'cvv' => $request->input('payment_method.cvv'),
                'card_holder' => $request->input('payment_method.card_holder'),
                'description' => "Subscription to {$plan->name}",
            ]);

            if (!$paymentResult['success']) {
                DB::rollBack();
                return $this->error($paymentResult['message'], 400, [
                    'gateway_error' => $paymentResult['error_code'] ?? null,
                ]);
            }


            // Create payment record
            $payment = Payment::create([
                'user_id' => $user->id,
                'plan_name' => $plan->slug,
                'transaction_id' => $paymentResult['transaction_id'],
                'gateway' => 'sandbox',
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'status' => 'completed',
                'type' => $plan->interval === 'one-time' ? 'one-time' : 'subscription',
                'card_last_four' => $paymentResult['card_last_four'],
                'card_brand' => $paymentResult['card_brand'],
                'description' => "Subscription to {$plan->name}",
                'gateway_response' => $paymentResult,
                'paid_at' => now(),
            ]);

            // Save the plan selection on the user record (replace any previous plan)
            $user->update(['current_plan' => $plan->slug]);

            DB::commit();

            return $this->success([
                'payment' => $payment,
                'message' => 'Subscription payment processed — plan set on user account',
            ], 'Payment processed successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            // log and return diagnostic message (helps tests detect root cause)
            \Illuminate\Support\Facades\Log::error('Subscribe exception', ['exception' => $e]);
            return $this->error('Failed to process subscription: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/subscriptions
     * Get current user's subscriptions.
     */
    // subscription lifecycle endpoints removed: payments now attach plan_name to user on successful payment

    /**
     * GET /api/subscriptions/active
     * Get current user's active subscription.
     */


    /**
     * POST /api/payments/process
     * Process a one-time payment (not subscription).
     */
    public function processPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.50|max:999999.99',
            'currency' => 'sometimes|string|size:3',
            'description' => 'sometimes|string|max:500',
            'payment_method' => 'required|array',
            'payment_method.card_number' => 'required|string|min:13|max:19',
            'payment_method.expiry_month' => 'required|string|size:2',
            'payment_method.expiry_year' => 'required|string|size:2',
            'payment_method.cvv' => 'required|string|min:3|max:4',
            'payment_method.card_holder' => 'required|string|max:255',
            'metadata' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $user = $request->user();

        $paymentResult = $this->gateway->processPayment([
            'amount' => $request->amount,
            'currency' => $request->currency ?? 'USD',
            'card_number' => $request->input('payment_method.card_number'),
            'expiry_month' => $request->input('payment_method.expiry_month'),
            'expiry_year' => $request->input('payment_method.expiry_year'),
            'cvv' => $request->input('payment_method.cvv'),
            'card_holder' => $request->input('payment_method.card_holder'),
            'description' => $request->description ?? 'One-time payment',
        ]);

        if (!$paymentResult['success']) {
            return $this->error($paymentResult['message'], 400, [
                'gateway_error' => $paymentResult['error_code'] ?? null,
            ]);
        }

        $payment = Payment::create([
            'user_id' => $user->id,
            'transaction_id' => $paymentResult['transaction_id'],
            'gateway' => 'sandbox',
            'amount' => $request->amount,
            'currency' => $request->currency ?? 'USD',
            'status' => 'completed',
            'type' => 'one-time',
            'card_last_four' => $paymentResult['card_last_four'],
            'card_brand' => $paymentResult['card_brand'],
            'description' => $request->description ?? 'One-time payment',
            'gateway_response' => $paymentResult,
            'metadata' => $request->metadata,
            'paid_at' => now(),
        ]);

        return $this->success($payment, 'Payment processed successfully', 201);
    }

    /**
     * GET /api/payments/{transactionId}
     * Verify/retrieve a payment by transaction ID.
     */
    public function verifyPayment(Request $request, string $transactionId): JsonResponse
    {
        $payment = Payment::where('transaction_id', $transactionId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$payment) {
            return $this->error('Payment not found', 404);
        }

        return $this->success([
            'payment' => $payment,
            'verified' => $payment->isCompleted(),
            'gateway_status' => $this->gateway->verifyTransaction($transactionId),
        ]);
    }

    /**
     * GET /api/payments
     * Get current user's payment history.
     */
    public function listPayments(Request $request): JsonResponse
    {
        $payments = $request->user()
            ->payments()
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->success($payments);
    }

    /**
     * GET /api/payments/last-plan
     * Return the last plan name the authenticated user paid for, with optional payment record.
     */
    public function lastPlan(Request $request): JsonResponse
    {
        $payment = Payment::where('user_id', $request->user()->id)
            ->whereNotNull('plan_name')
            ->orderByDesc('paid_at')
            ->first();

        if (! $payment) {
            return $this->success(['plan' => null, 'payment' => null], 'No plan purchase found', 200);
        }

        $plan = SubscriptionPlan::where('slug', $payment->plan_name)->first();

        return $this->success([
            'plan' => $plan ? $plan : ['slug' => $payment->plan_name],
            'payment' => $payment,
        ]);
    }

    /**
     * POST /api/payments/refund/{transactionId}
     * Request a refund for a payment.
     */
    public function refundPayment(Request $request, string $transactionId): JsonResponse
    {
        $payment = Payment::where('transaction_id', $transactionId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$payment) {
            return $this->error('Payment not found', 404);
        }

        if ($payment->status === 'refunded') {
            return $this->error('Payment has already been refunded', 400);
        }

        if ($payment->status !== 'completed') {
            return $this->error('Only completed payments can be refunded', 400);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        // Process refund through sandbox gateway
        $refundResult = $this->gateway->processRefund($transactionId, (float)$payment->amount, $request->reason);

        if (!$refundResult['success']) {
            return $this->error($refundResult['message'], 400);
        }

        $payment->update([
            'status' => 'refunded',
            'metadata' => array_merge($payment->metadata ?? [], [
                'refund_reason' => $request->reason,
                'refund_transaction_id' => $refundResult['refund_transaction_id'],
                'refunded_at' => now()->toISOString(),
            ]),
        ]);

        return $this->success($payment->fresh(), 'Refund processed successfully');
    }

    /**
     * POST /api/payments/webhook
     * Handle payment gateway webhooks (sandbox simulation).
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_type' => 'required|string',
            'transaction_id' => 'required|string',
            'payload' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        // Verify webhook signature (sandbox always passes)
        $signatureValid = $this->gateway->verifyWebhookSignature(
            $request->header('X-Webhook-Signature'),
            $request->getContent()
        );

        if (!$signatureValid) {
            return $this->error('Invalid webhook signature', 401);
        }

        $eventType = $request->event_type;
        $transactionId = $request->transaction_id;

        $payment = Payment::where('transaction_id', $transactionId)->first();

        switch ($eventType) {
            case 'payment.completed':
                if ($payment) {
                    $payment->update(['status' => 'completed', 'paid_at' => now()]);
                }
                break;

            case 'payment.failed':
                if ($payment) {
                    $payment->update(['status' => 'failed']);
                }
                break;

            // subscription lifecycle events are not handled in sandbox (no subscription records created)

            default:
                // Unknown event type, log but don't fail
                break;
        }

        return $this->success([
            'received' => true,
            'event_type' => $eventType,
        ], 'Webhook processed');
    }

    /**
     * POST /api/payments/revert-plan
     * Revert or change current plan without charging — creates an audit payment record of type 'revert'.
     */
    public function revertPlan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to_plan' => 'sometimes|nullable|string|exists:subscription_plans,slug',
            'reason' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $user = $request->user();

        // Determine target plan (nullable clears plan)
        $toPlan = $request->to_plan ? SubscriptionPlan::where('slug', $request->to_plan)->first() : null;

        DB::beginTransaction();
        try {
            // Update user's current_plan
            $user->update(['current_plan' => $toPlan ? $toPlan->slug : null]);

            // Record an audit / history payment-like record
            $payment = Payment::create([
                'user_id' => $user->id,
                'transaction_id' => Payment::generateTransactionId(),
                'gateway' => 'system',
                'amount' => 0.00,
                'currency' => $toPlan ? $toPlan->currency : 'USD',
                'status' => 'completed',
                'type' => 'revert',
                'description' => $toPlan ? "Reverted plan to {$toPlan->name}" : 'Cleared current plan',
                'plan_name' => $toPlan ? $toPlan->slug : null,
                'gateway_response' => ['reason' => $request->reason ?? null],
                'paid_at' => now(),
            ]);

            DB::commit();

            return $this->success([
                'payment' => $payment,
                'user' => $user->fresh(),
            ], 'Plan reverted', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Revert plan exception', ['exception' => $e]);
            return $this->error('Failed to revert plan: ' . $e->getMessage(), 500);
        }
    }
}
