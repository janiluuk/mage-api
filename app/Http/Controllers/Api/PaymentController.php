<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Constant\OrderStatusConstant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Stripe\StripeClient;
use Stripe\Exception\SignatureVerificationException;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Create a payment intent for an order.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::findOrFail($request->order_id);

        // Verify user owns this order
        if ($order->user_id !== auth()->id()) {
            return response()->json([
                'error' => 'You do not have permission to process payment for this order.'
            ], 403);
        }

        // Check if order is in correct status
        if ($order->status !== OrderStatusConstant::UNPAID) {
            return response()->json([
                'error' => 'This order cannot be paid. Current status: ' . $order->status
            ], 400);
        }

        try {
            // Create payment intent
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => (int)($order->total_cost * 100), // Convert to cents
                'currency' => 'usd',
                'metadata' => [
                    'order_id' => $order->id,
                    'user_id' => auth()->id(),
                ],
                'description' => "Order #{$order->id}",
            ]);

            // Create payment record
            OrderPayment::create([
                'order_id' => $order->id,
                'amount' => $order->total_cost,
                'status' => 'pending',
                'type' => 'Stripe',
                'session_id' => $paymentIntent->id,
            ]);

            return response()->json([
                'clientSecret' => $paymentIntent->client_secret,
                'paymentIntentId' => $paymentIntent->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Stripe payment intent creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to create payment intent. Please try again.'
            ], 500);
        }
    }

    /**
     * Handle Stripe webhook events.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $webhookSecret
            );
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe webhook: Invalid payload', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook: Invalid signature', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentSucceeded($event->data->object);
                break;
            
            case 'payment_intent.payment_failed':
                $this->handlePaymentFailed($event->data->object);
                break;
            
            default:
                Log::info('Stripe webhook: Unhandled event type', ['type' => $event->type]);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle successful payment.
     *
     * @param object $paymentIntent
     * @return void
     */
    private function handlePaymentSucceeded(object $paymentIntent): void
    {
        $orderId = $paymentIntent->metadata->order_id ?? null;
        
        if (!$orderId) {
            Log::error('Stripe webhook: Missing order_id in payment intent metadata');
            return;
        }

        $order = Order::find($orderId);
        
        if (!$order) {
            Log::error('Stripe webhook: Order not found', ['order_id' => $orderId]);
            return;
        }

        // Update order status to paid
        $order->status = OrderStatusConstant::PAID;
        $order->save();

        // Update payment record
        $payment = OrderPayment::where('session_id', $paymentIntent->id)->first();
        if ($payment) {
            $payment->status = 'succeeded';
            $payment->save();
        }

        // TODO: Enroll GPU credits to user wallet
        // This should trigger a finance operation to add credits based on the product
        
        Log::info('Payment succeeded', [
            'order_id' => $orderId,
            'payment_intent_id' => $paymentIntent->id,
            'amount' => $paymentIntent->amount / 100,
        ]);
    }

    /**
     * Handle failed payment.
     *
     * @param object $paymentIntent
     * @return void
     */
    private function handlePaymentFailed(object $paymentIntent): void
    {
        $orderId = $paymentIntent->metadata->order_id ?? null;
        
        if (!$orderId) {
            Log::error('Stripe webhook: Missing order_id in payment intent metadata');
            return;
        }

        // Update payment record
        $payment = OrderPayment::where('session_id', $paymentIntent->id)->first();
        if ($payment) {
            $payment->status = 'failed';
            $payment->save();
        }

        Log::warning('Payment failed', [
            'order_id' => $orderId,
            'payment_intent_id' => $paymentIntent->id,
            'error' => $paymentIntent->last_payment_error->message ?? 'Unknown error',
        ]);
    }
}
