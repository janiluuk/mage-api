<?php

namespace Tests\Feature\Payment;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Category;
use App\Constant\OrderStatusConstant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

/**
 * Tests for GPU Credit Enrollment functionality
 * 
 * These tests verify that GPU credits are properly enrolled to user accounts
 * after successful payment processing.
 */
class GpuCreditEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Config::set('services.stripe.secret', 'sk_test_mock');
        Config::set('services.stripe.webhook_secret', 'whsec_test_mock');
    }

    public function test_credits_enrolled_with_single_product(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'title' => '100 GPU Credits',
            'price' => 10.00,
            'quantity' => 100, // Represents GPU credits
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
            'quantity' => 1,
            'unit_price' => 10.00,
        ]);

        // Test the enrollment method
        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('enrollCreditsForOrder');
        $method->setAccessible(true);

        $method->invoke($controller, $order);

        // Verify finance operation was created
        $this->assertDatabaseHas('finance_operations_histories', [
            'user_id' => $user->id,
            'type' => 1, // ENROLLMENT constant
            'money' => 100,
        ]);
    }

    public function test_credits_multiplied_by_order_quantity(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 10.00,
            'quantity' => 50, // 50 credits per product
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'products_cost' => 30.00,
            'total_cost' => 30.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3, // Buying 3 units
            'unit_price' => 10.00,
        ]);

        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('enrollCreditsForOrder');
        $method->setAccessible(true);

        $method->invoke($controller, $order);

        // Should have 50 * 3 = 150 credits
        $this->assertDatabaseHas('finance_operations_histories', [
            'user_id' => $user->id,
            'money' => 150,
        ]);
    }

    public function test_credits_summed_across_multiple_products(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        
        $product1 = Product::factory()->create([
            'category_id' => $category->id,
            'quantity' => 50,
        ]);

        $product2 = Product::factory()->create([
            'category_id' => $category->id,
            'quantity' => 75,
        ]);

        $product3 = Product::factory()->create([
            'category_id' => $category->id,
            'quantity' => 25,
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product1->id,
            'quantity' => 2, // 50 * 2 = 100
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 1, // 75 * 1 = 75
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product3->id,
            'quantity' => 1, // 25 * 1 = 25
        ]);

        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('enrollCreditsForOrder');
        $method->setAccessible(true);

        $method->invoke($controller, $order);

        // Total: 100 + 75 + 25 = 200
        $this->assertDatabaseHas('finance_operations_histories', [
            'user_id' => $user->id,
            'money' => 200,
        ]);
    }

    public function test_no_enrollment_when_product_quantity_is_zero(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'quantity' => 0, // No credits
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('enrollCreditsForOrder');
        $method->setAccessible(true);

        $method->invoke($controller, $order);

        // No finance operation should exist
        $this->assertDatabaseMissing('finance_operations_histories', [
            'user_id' => $user->id,
            'type' => 1, // ENROLLMENT
        ]);
    }

    public function test_no_enrollment_when_order_has_no_items(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        // Order with no items
        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('enrollCreditsForOrder');
        $method->setAccessible(true);

        // Should not throw exception
        $method->invoke($controller, $order);

        // No finance operation should exist
        $this->assertDatabaseMissing('finance_operations_histories', [
            'user_id' => $user->id,
        ]);
    }

    public function test_enrollment_creates_correct_finance_operation_type(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'quantity' => 100,
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('enrollCreditsForOrder');
        $method->setAccessible(true);

        $method->invoke($controller, $order);

        // Verify correct type (ENROLLMENT = 1)
        $this->assertDatabaseHas('finance_operations_histories', [
            'user_id' => $user->id,
            'type' => 1,
            'status' => 2, // EXECUTED = 2
        ]);
    }

    public function test_large_credit_amounts_handled_correctly(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'quantity' => 10000, // Large credit pack
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 5, // 5 large packs
        ]);

        $controller = new \App\Http\Controllers\Api\PaymentController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('enrollCreditsForOrder');
        $method->setAccessible(true);

        $method->invoke($controller, $order);

        // 10000 * 5 = 50000 credits
        $this->assertDatabaseHas('finance_operations_histories', [
            'user_id' => $user->id,
            'money' => 50000,
        ]);
    }
}
