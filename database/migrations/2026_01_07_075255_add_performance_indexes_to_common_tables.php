<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds indexes to improve query performance for frequently accessed columns
     * across multiple tables. These indexes support filtering, sorting, and
     * relationship lookups.
     *
     * @return void
     */
    public function up(): void
    {
        // Orders table indexes
        Schema::table('orders', function (Blueprint $table) {
            // Index for filtering orders by customer
            if (!$this->indexExists('orders', 'idx_orders_user_customer_id')) {
                $table->index('user_customer_id', 'idx_orders_user_customer_id');
            }
            
            // Index for filtering orders by seller
            if (!$this->indexExists('orders', 'idx_orders_user_seller_id')) {
                $table->index('user_seller_id', 'idx_orders_user_seller_id');
            }
            
            // Composite index for status filtering with date ordering
            if (!$this->indexExists('orders', 'idx_orders_status_created')) {
                $table->index(['status', 'created_at'], 'idx_orders_status_created');
            }
        });

        // Finance operations history indexes
        Schema::table('finance_operations_history', function (Blueprint $table) {
            // Composite index for user finance operations with status
            if (!$this->indexExists('finance_operations_history', 'idx_finance_user_status')) {
                $table->index(['user_id', 'status'], 'idx_finance_user_status');
            }
            
            // Index for filtering by type
            if (!$this->indexExists('finance_operations_history', 'idx_finance_type')) {
                $table->index('type', 'idx_finance_type');
            }
            
            // Index for wallet lookups
            if (!$this->indexExists('finance_operations_history', 'idx_finance_wallet_id')) {
                $table->index('wallet_id', 'idx_finance_wallet_id');
            }
        });

        // Products table indexes
        Schema::table('products', function (Blueprint $table) {
            // Composite index for category filtering with active status
            if (!$this->indexExists('products', 'idx_products_category_status')) {
                $table->index(['category_id', 'status'], 'idx_products_category_status');
            }
            
            // Index for active products filtering
            if (!$this->indexExists('products', 'idx_products_status')) {
                $table->index('status', 'idx_products_status');
            }
        });

        // Support requests indexes
        Schema::table('support_requests', function (Blueprint $table) {
            // Composite index for user support requests with status
            if (!$this->indexExists('support_requests', 'idx_support_user_status')) {
                $table->index(['user_id', 'status'], 'idx_support_user_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_user_customer_id');
            $table->dropIndex('idx_orders_user_seller_id');
            $table->dropIndex('idx_orders_status_created');
        });

        Schema::table('finance_operations_history', function (Blueprint $table) {
            $table->dropIndex('idx_finance_user_status');
            $table->dropIndex('idx_finance_type');
            $table->dropIndex('idx_finance_wallet_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_category_status');
            $table->dropIndex('idx_products_status');
        });

        Schema::table('support_requests', function (Blueprint $table) {
            $table->dropIndex('idx_support_user_status');
        });
    }
    
    /**
     * Check if an index exists on a table.
     * 
     * Uses Laravel's Schema facade to check for index existence
     * in a way that's compatible with different database drivers.
     *
     * @param string $table
     * @param string $index
     * @return bool
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $connection = Schema::getConnection();
            $doctrineSchemaManager = $connection->getDoctrineSchemaManager();
            $doctrineTable = $doctrineSchemaManager->introspectTable($table);
            
            return $doctrineTable->hasIndex($index);
        } catch (\Exception $e) {
            // If we can't check, assume index doesn't exist to allow creation attempt
            return false;
        }
    }
};
