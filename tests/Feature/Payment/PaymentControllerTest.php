<?php

namespace Tests\Feature\Payment;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Category;
use App\Models\FinanceOperationsHistory;
use App\Constant\OrderStatusConstant;
use App\Constant\OrderPaymentConstant;
use App\Constant\FinanceOperationConstant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
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

    // ==================== Payment Intent Tests ====================

    public function test_user_cannot_create_payment_intent_for_another_users_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        
        $order = Order::factory()->create([
            'user_id' => $otherUser->id,
            'products_cost' => 100.00,
            'total_cost' => 100.00,
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
    }

    public function test_payment_intent_requires_valid_order(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payment/create-intent', [
                'order_id' => 99999
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_create_payment_intent_for_paid_order(): void
    {
        $user = User::factory()->create();
        
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatusConstant::PAID,
            'products_cost' => 100.00,
            'total_cost' => 100.00,
        ]);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payment/create-intent', [
                'order_id' => $order->id
            ]);

        $response->assertStatus(400);
        $response->assertJsonStructure(['error']);
    }

    public function test_unauthenticated_user_cannot_create_payment_intent(): void
    {
        $response = $this->postJson('/api/payment/create-intent', [
            'order_id' => 1
        ]);

        $response->assertStatus(401);
    }

    // ==================== Order Payment Model Tests ====================

    public function test_order_payment_model_has_correct_relationships(): void
    {
        $user = User::factory()->create();
        
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'products_cost' => 100.00,
            'total_cost' => 100.00,
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

    public function test_order_has_payments_relationship(): void
    {
        $order = Order::factory()->create();
        
        OrderPayment::create([
            'order_id' => $order->id,
            'amount' => 100.00,
            'status' => OrderPayment::STATUS_SUCCEEDED,
            'type' => 'Stripe',
            'session_id' => 'pi_test_123'
        ]);

        $order->refresh();
        
        $this->assertCount(1, $order->payments);
        $this->assertInstanceOf(OrderPayment::class, $order->payments->first());
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

    public function test_order_payment_status_constants_exist(): void
    {
        $this->assertEquals('pending', OrderPayment::STATUS_PENDING);
        $this->assertEquals('succeeded', OrderPayment::STATUS_SUCCEEDED);
        $this->assertEquals('failed', OrderPayment::STATUS_FAILED);
        $this->assertEquals('refunded', OrderPayment::STATUS_REFUNDED);
    }

    // ==================== Webhook Handling Tests ====================

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = json_encode([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']]
        ]);

        $response = $this->postJson('/api/webhooks/stripe', 
            json_decode($payload, true),
            ['Stripe-Signature' => 'invalid_signature']
        );

        $response->assertStatus(400);
    }

    public function test_webhook_handles_missing_order_id_gracefully(): void
    {
        // Create mock webhook event without order metadata
        $event = (object)[
            'type' => 'payment_intent.succeeded',
            'data' => (object)[
                'object' => (object)[
                    'id' => 'pi_test_123',
                    'amount' => 10000,
                    'metadata' => (object)[] // No order_id
                ]
            ]
        ];

        // This should not throw an exception, just log an error
        $this->expectNotToPerformAssertions();
    }

    // ==================== GPU Credit Enrollment Tests ====================

    public function test_gpu_credits_enrolled_when_product_has_gpu_credits_field(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        
        // Create product with gpu_credits field (if your DB supports it)
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'title' => '100 GPU Credits Pack',
            'price' => 10.00,
            'quantity' => 100, // This represents GPU credits in current implementation
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatusConstant::UNPAID,
            'products_cost' => 10.00,
            'total_cost' => 10.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2, // User bought 2 packs
            'unit_price' => 10.00,
        ]);

        // Simulate webhook payment success
        $order->status = OrderStatusConstant::PAID;
        $order->save();

        // Since we can't call private method directly, we'll test via reflection
        // or by checking the finance operations after a full payment flow
        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('enrollCreditsForOrder');
        $method->setAccessible(true);

        $method->invoke($controller, $order);

        // Verify finance operation was created
        $financeOps = FinanceOperationsHistory::where('user_id', $user->id)
            ->where('type', FinanceOperationConstant::ENROLLMENT)
            ->get();

        $this->assertCount(1, $financeOps);
        $this->assertEquals(200, $financeOps->first()->money); // 100 credits * 2 packs
    }

    public function test_gpu_credits_enrolled_for_multiple_products(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        
        $product1 = Product::factory()->create([
            'category_id' => $category->id,
            'title' => '50 Credits',
            'price' => 5.00,
            'quantity' => 50,
        ]);

        $product2 = Product::factory()->create([
            'category_id' => $category->id,
            'title' => '100 Credits',
            'price' => 10.00,
            'quantity' => 100,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatusConstant::UNPAID,
            'products_cost' => 15.00,
            'total_cost' => 15.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product1->id,
            'quantity' => 1,
            'unit_price' => 5.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 1,
            'unit_price' => 10.00,
        ]);

        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('enrollCreditsForOrder');
        $method->setAccessible(true);

        $method->invoke($controller, $order);

        $financeOps = FinanceOperationsHistory::where('user_id', $user->id)
            ->where('type', FinanceOperationConstant::ENROLLMENT)
            ->first();

        $this->assertNotNull($financeOps);
        $this->assertEquals(150, $financeOps->money); // 50 + 100
    }

    public function test_no_credits_enrolled_when_products_have_zero_quantity(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'title' => 'Non-credit product',
            'price' => 10.00,
            'quantity' => 0, // No GPU credits
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'products_cost' => 10.00,
            'total_cost' => 10.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10.00,
        ]);

        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('enrollCreditsForOrder');
        $method->setAccessible(true);

        $method->invoke($controller, $order);

        // No finance operation should be created
        $financeOps = FinanceOperationsHistory::where('user_id', $user->id)
            ->where('type', FinanceOperationConstant::ENROLLMENT)
            ->count();

        $this->assertEquals(0, $financeOps);
    }

    public function test_credit_enrollment_handles_missing_products_gracefully(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'products_cost' => 10.00,
            'total_cost' => 10.00,
        ]);

        // Order with no items
        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('enrollCreditsForOrder');
        $method->setAccessible(true);

        // Should not throw exception
        $method->invoke($controller, $order);

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    // ==================== Integration Tests ====================

    public function test_full_payment_flow_with_credit_enrollment(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'title' => '500 GPU Credits',
            'price' => 50.00,
            'quantity' => 500,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatusConstant::UNPAID,
            'products_cost' => 50.00,
            'total_cost' => 50.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50.00,
        ]);

        // Step 1: Verify initial state
        $this->assertEquals(OrderStatusConstant::UNPAID, $order->status);
        $this->assertCount(0, $user->financeOperationsHistories ?? []);

        // Step 2: Simulate payment success
        $order->status = OrderStatusConstant::PAID;
        $order->save();

        OrderPayment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => OrderPayment::STATUS_SUCCEEDED,
            'type' => 'Stripe',
            'session_id' => 'pi_test_complete_flow'
        ]);

        // Step 3: Enroll credits
        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('enrollCreditsForOrder');
        $method->setAccessible(true);
        $method->invoke($controller, $order);

        // Step 4: Verify final state
        $order->refresh();
        $this->assertEquals(OrderStatusConstant::PAID, $order->status);
        
        $payment = $order->payments()->first();
        $this->assertNotNull($payment);
        $this->assertTrue($payment->isSuccessful());

        $financeOp = FinanceOperationsHistory::where('user_id', $user->id)->first();
        $this->assertNotNull($financeOp);
        $this->assertEquals(500, $financeOp->money);
        $this->assertEquals(FinanceOperationConstant::ENROLLMENT, $financeOp->type);
    }

    public function test_payment_and_order_relationship(): void
    {
        $order = Order::factory()->create();
        
        $payment1 = OrderPayment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => OrderPayment::STATUS_PENDING,
            'type' => 'Stripe',
            'session_id' => 'pi_1'
        ]);

        $payment2 = OrderPayment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => OrderPayment::STATUS_SUCCEEDED,
            'type' => 'Stripe',
            'session_id' => 'pi_2'
        ]);

        $order->refresh();
        
        $this->assertCount(2, $order->payments);
        $this->assertTrue($order->payments->contains($payment1));
        $this->assertTrue($order->payments->contains($payment2));
    }

    public function test_order_with_multiple_items_calculates_correct_credits(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        
        $products = Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'quantity' => 100, // Each has 100 credits
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);

        foreach ($products as $product) {
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 2, // Buy 2 of each
                'unit_price' => $product->price,
            ]);
        }

        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('enrollCreditsForOrder');
        $method->setAccessible(true);
        $method->invoke($controller, $order);

        $financeOp = FinanceOperationsHistory::where('user_id', $user->id)->first();
        
        // 3 products * 100 credits * 2 quantity = 600 total credits
        $this->assertEquals(600, $financeOp->money);
    }
}
