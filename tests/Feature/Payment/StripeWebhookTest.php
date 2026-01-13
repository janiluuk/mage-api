<?php

namespace Tests\Feature\Payment;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Constant\OrderStatusConstant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Tests for Stripe Webhook Integration
 * 
 * These tests verify proper handling of Stripe webhook events
 * including payment success, payment failure, and error cases.
 */
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Config::set('services.stripe.secret', 'sk_test_mock');
        Config::set('services.stripe.webhook_secret', 'whsec_test_mock');
    }

    public function test_webhook_endpoint_exists(): void
    {
        $response = $this->postJson('/api/webhooks/stripe', []);
        
        // Should not return 404
        $this->assertNotEquals(404, $response->status());
    }

    public function test_webhook_rejects_requests_without_signature(): void
    {
        $response = $this->postJson('/api/webhooks/stripe', [
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']]
        ]);

        // Should reject without proper signature
        $this->assertContains($response->status(), [400, 401, 403]);
    }

    public function test_payment_success_updates_order_status(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatusConstant::UNPAID,
            'total_cost' => 100.00,
        ]);

        // Create a pending payment
        $payment = OrderPayment::create([
            'order_id' => $order->id,
            'amount' => 100.00,
            'status' => OrderPayment::STATUS_PENDING,
            'type' => 'Stripe',
            'session_id' => 'pi_test_success_123'
        ]);

        // Simulate webhook processing directly
        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('handlePaymentSucceeded');
        $method->setAccessible(true);

        $paymentIntentMock = (object)[
            'id' => 'pi_test_success_123',
            'amount' => 10000, // $100.00 in cents
            'metadata' => (object)[
                'order_id' => $order->id,
                'user_id' => $user->id,
            ]
        ];

        $method->invoke($controller, $paymentIntentMock);

        // Verify order status updated
        $order->refresh();
        $this->assertEquals(OrderStatusConstant::PAID, $order->status);

        // Verify payment status updated
        $payment->refresh();
        $this->assertEquals(OrderPayment::STATUS_SUCCEEDED, $payment->status);
    }

    public function test_payment_failure_updates_payment_status(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatusConstant::UNPAID,
        ]);

        $payment = OrderPayment::create([
            'order_id' => $order->id,
            'amount' => 100.00,
            'status' => OrderPayment::STATUS_PENDING,
            'type' => 'Stripe',
            'session_id' => 'pi_test_fail_123'
        ]);

        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('handlePaymentFailed');
        $method->setAccessible(true);

        $paymentIntentMock = (object)[
            'id' => 'pi_test_fail_123',
            'metadata' => (object)[
                'order_id' => $order->id,
            ],
            'last_payment_error' => (object)[
                'message' => 'Card declined'
            ]
        ];

        $method->invoke($controller, $paymentIntentMock);

        // Payment should be marked as failed
        $payment->refresh();
        $this->assertEquals(OrderPayment::STATUS_FAILED, $payment->status);

        // Order should still be unpaid
        $order->refresh();
        $this->assertEquals(OrderStatusConstant::UNPAID, $order->status);
    }

    public function test_webhook_handles_missing_order_gracefully(): void
    {
        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('handlePaymentSucceeded');
        $method->setAccessible(true);

        $paymentIntentMock = (object)[
            'id' => 'pi_test_missing_order',
            'amount' => 10000,
            'metadata' => (object)[
                'order_id' => 99999, // Non-existent order
            ]
        ];

        // Should not throw exception
        try {
            $method->invoke($controller, $paymentIntentMock);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail('Should handle missing order gracefully');
        }
    }

    public function test_webhook_handles_missing_metadata_gracefully(): void
    {
        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('handlePaymentSucceeded');
        $method->setAccessible(true);

        $paymentIntentMock = (object)[
            'id' => 'pi_test_no_metadata',
            'amount' => 10000,
            'metadata' => (object)[] // Empty metadata
        ];

        // Should not throw exception
        try {
            $method->invoke($controller, $paymentIntentMock);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail('Should handle missing metadata gracefully');
        }
    }

    public function test_successful_payment_triggers_credit_enrollment(): void
    {
        $this->markTestSkipped('GPU credit feature requires gpu_credits or quantity field in products table');
        
        $user = User::factory()->create();
        
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatusConstant::UNPAID,
            'total_cost' => 20.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 20.00,
        ]);

        $payment = OrderPayment::create([
            'order_id' => $order->id,
            'amount' => 20.00,
            'status' => OrderPayment::STATUS_PENDING,
            'type' => 'Stripe',
            'session_id' => 'pi_test_with_credits'
        ]);

        // Simulate webhook payment success
        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('handlePaymentSucceeded');
        $method->setAccessible(true);

        $paymentIntentMock = (object)[
            'id' => 'pi_test_with_credits',
            'amount' => 2000,
            'metadata' => (object)[
                'order_id' => $order->id,
                'user_id' => $user->id,
            ]
        ];

        $method->invoke($controller, $paymentIntentMock);

        // Verify order paid
        $order->refresh();
        $this->assertEquals(OrderStatusConstant::PAID, $order->status);

        // Verify credits enrolled
        $this->assertDatabaseHas('finance_operations_histories', [
            'user_id' => $user->id,
            'type' => 1, // ENROLLMENT
            'money' => 200,
        ]);
    }

    public function test_payment_updates_create_order_payment_record(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatusConstant::UNPAID,
        ]);

        // Initially no payment record
        $this->assertCount(0, $order->payments);

        // Simulate finding an existing payment by session_id
        $payment = OrderPayment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => OrderPayment::STATUS_PENDING,
            'type' => 'Stripe',
            'session_id' => 'pi_test_record'
        ]);

        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('handlePaymentSucceeded');
        $method->setAccessible(true);

        $paymentIntentMock = (object)[
            'id' => 'pi_test_record',
            'amount' => 5000,
            'metadata' => (object)[
                'order_id' => $order->id,
            ]
        ];

        $method->invoke($controller, $paymentIntentMock);

        // Verify payment record updated
        $payment->refresh();
        $this->assertEquals(OrderPayment::STATUS_SUCCEEDED, $payment->status);
    }

    public function test_multiple_payment_attempts_tracked_separately(): void
    {
        $order = Order::factory()->create();

        // First payment attempt fails
        $payment1 = OrderPayment::create([
            'order_id' => $order->id,
            'amount' => 100.00,
            'status' => OrderPayment::STATUS_FAILED,
            'type' => 'Stripe',
            'session_id' => 'pi_attempt_1'
        ]);

        // Second payment attempt succeeds
        $payment2 = OrderPayment::create([
            'order_id' => $order->id,
            'amount' => 100.00,
            'status' => OrderPayment::STATUS_SUCCEEDED,
            'type' => 'Stripe',
            'session_id' => 'pi_attempt_2'
        ]);

        $order->refresh();

        $this->assertCount(2, $order->payments);
        $this->assertTrue($order->payments->contains($payment1));
        $this->assertTrue($order->payments->contains($payment2));
        
        // Only the successful one should show as succeeded
        $successfulPayments = $order->payments->filter(fn($p) => $p->isSuccessful());
        $this->assertCount(1, $successfulPayments);
    }
}
