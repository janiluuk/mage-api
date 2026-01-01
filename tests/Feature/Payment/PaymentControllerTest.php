<?php

namespace Tests\Feature\Payment;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderPayment;
use App\Constant\OrderConstant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock Stripe configuration
        Config::set('services.stripe.secret', 'sk_test_mock');
        Config::set('services.stripe.webhook_secret', 'whsec_test_mock');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_user_cannot_create_payment_intent_for_another_users_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $product = Product::factory()->create();
        
        $order = Order::factory()->create([
            'user_customer_id' => $otherUser->id,
            'product_id' => $product->id,
            'status' => OrderConstant::CREATED,
            'order_price' => 100.00
        ]);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payment/create-intent', [
                'order_id' => $order->id
            ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'You do not have permission to process payment for this order.']);
    }

    public function test_payment_intent_requires_order_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payment/create-intent', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['order_id']);
    }

    public function test_payment_intent_requires_valid_order(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payment/create-intent', [
                'order_id' => 99999
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['order_id']);
    }

    public function test_cannot_create_payment_intent_for_non_created_order(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        
        $order = Order::factory()->create([
            'user_customer_id' => $user->id,
            'product_id' => $product->id,
            'status' => OrderConstant::PAID,
            'order_price' => 100.00
        ]);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payment/create-intent', [
                'order_id' => $order->id
            ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['error']);
    }

    public function test_unauthenticated_user_cannot_create_payment_intent(): void
    {
        $response = $this->postJson('/api/payment/create-intent', [
            'order_id' => 1
        ]);

        $response->assertStatus(401);
    }

    public function test_order_payment_model_has_correct_relationships(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        
        $order = Order::factory()->create([
            'user_customer_id' => $user->id,
            'product_id' => $product->id,
            'status' => OrderConstant::CREATED
        ]);

        $payment = OrderPayment::create([
            'order_id' => $order->id,
            'amount' => 100.00,
            'status' => OrderPayment::STATUS_PENDING,
            'type' => 'Stripe',
            'session_id' => 'pi_test_123'
        ]);

        $this->assertInstanceOf(Order::class, $payment->order);
        $this->assertEquals($order->id, $payment->order->id);
    }

    public function test_order_payment_status_helpers(): void
    {
        $payment = new OrderPayment([
            'status' => OrderPayment::STATUS_SUCCEEDED
        ]);

        $this->assertTrue($payment->isSuccessful());
        $this->assertFalse($payment->isPending());
        $this->assertFalse($payment->isFailed());

        $payment->status = OrderPayment::STATUS_PENDING;
        $this->assertTrue($payment->isPending());
        $this->assertFalse($payment->isSuccessful());

        $payment->status = OrderPayment::STATUS_FAILED;
        $this->assertTrue($payment->isFailed());
        $this->assertFalse($payment->isSuccessful());
    }
}
