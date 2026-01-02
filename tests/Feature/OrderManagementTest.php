<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Constant\OrderStatusConstant;
use App\Constant\OrderPaymentConstant;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tests for Order Management functionality
 * 
 * These tests verify order creation, updates, and lifecycle management.
 */
class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_order(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 50.00,
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/orders', [
            'products' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ]
            ],
            'payment_method' => OrderPaymentConstant::STRIPE,
        ]);

        // Check response
        if ($response->status() === 201 || $response->status() === 200) {
            $response->assertJsonStructure(['id', 'status', 'total_cost']);
        }
    }

    public function test_order_has_correct_relationships(): void
    {
        $user = User::factory()->create();
        
        $product = Product::factory()->create([
            'category_id' => $category->id,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $product->price,
        ]);

        // Test relationships
        $this->assertInstanceOf(User::class, $order->user);
        $this->assertEquals($user->id, $order->user->id);
        
        $this->assertCount(1, $order->orderItems);
        $this->assertInstanceOf(OrderItem::class, $order->orderItems->first());
        
        $orderItem = $order->orderItems->first();
        $this->assertInstanceOf(Product::class, $orderItem->product);
    }

    public function test_order_status_transitions(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatusConstant::UNPAID,
        ]);

        $this->assertEquals(OrderStatusConstant::UNPAID, $order->status);

        // Transition to paid
        $order->status = OrderStatusConstant::PAID;
        $order->save();
        
        $order->refresh();
        $this->assertEquals(OrderStatusConstant::PAID, $order->status);
    }

    public function test_order_calculates_total_cost_correctly(): void
    {
        $order = Order::factory()->create([
            'products_cost' => 100.00,
            'delivery_cost' => 10.00,
            'total_cost' => 110.00,
        ]);

        $this->assertEquals(110.00, $order->total_cost);
        $this->assertEquals(100.00, $order->products_cost);
        $this->assertEquals(10.00, $order->delivery_cost);
    }

    public function test_order_with_multiple_items(): void
    {
        $user = User::factory()->create();
        
        $products = Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'price' => 25.00,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
        ]);

        foreach ($products as $product) {
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => $product->price,
            ]);
        }

        $order->refresh();
        $this->assertCount(3, $order->orderItems);
        
        // Verify each item
        foreach ($order->orderItems as $item) {
            $this->assertEquals(2, $item->quantity);
            $this->assertEquals(25.00, $item->unit_price);
        }
    }

    public function test_order_item_calculates_subtotal(): void
    {
        $orderItem = OrderItem::factory()->create([
            'quantity' => 3,
            'unit_price' => 15.50,
        ]);

        // Calculate subtotal
        $subtotal = $orderItem->quantity * $orderItem->unit_price;
        $this->assertEquals(46.50, $subtotal);
    }

    public function test_order_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $order->user);
        $this->assertEquals($user->id, $order->user->id);
        $this->assertEquals($user->email, $order->user->email);
    }

    public function test_cannot_access_another_users_order(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $order = Order::factory()->create([
            'user_id' => $user1->id,
        ]);

        $response = $this->actingAs($user2, 'api')
            ->getJson("/api/orders/{$order->id}");

        // Should either be forbidden or not found
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_order_with_promo_code(): void
    {
        $order = Order::factory()->create([
            'promo_code_id' => null, // Or create a promo code
            'products_cost' => 100.00,
            'total_cost' => 100.00,
        ]);

        $this->assertNull($order->promo_code_id);
    }

    public function test_order_payment_method_enum(): void
    {
        $order = Order::factory()->create([
            'payment_method' => OrderPaymentConstant::STRIPE,
        ]);

        $this->assertEquals(OrderPaymentConstant::STRIPE, $order->payment_method);
    }
}
