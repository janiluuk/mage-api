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
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                // Index for filtering orders by customer (if column exists)
                if (Schema::hasColumn('orders', 'user_customer_id') && !$this->indexExists('orders', 'idx_orders_user_customer_id')) {
                    $table->index('user_customer_id', 'idx_orders_user_customer_id');
                }
                
                // Index for filtering orders by seller (if column exists)
                if (Schema::hasColumn('orders', 'user_seller_id') && !$this->indexExists('orders', 'idx_orders_user_seller_id')) {
                    $table->index('user_seller_id', 'idx_orders_user_seller_id');
                }
                
                // Composite index for status filtering with date ordering (if columns exist)
                if (Schema::hasColumn('orders', 'status') && Schema::hasColumn('orders', 'created_at') && !$this->indexExists('orders', 'idx_orders_status_created')) {
                    $table->index(['status', 'created_at'], 'idx_orders_status_created');
                }
                
                // Alternative: Index for user_id if it exists instead of user_customer_id
                if (Schema::hasColumn('orders', 'user_id') && !$this->indexExists('orders', 'idx_orders_user_id')) {
                    $table->index('user_id', 'idx_orders_user_id');
                }
            });
        }

        // Finance operations histories indexes (note: table name is plural)
        if (Schema::hasTable('finance_operations_histories')) {
            Schema::table('finance_operations_histories', function (Blueprint $table) {
                // Composite index for user finance operations with status
                if (Schema::hasColumn('finance_operations_histories', 'user_id') && Schema::hasColumn('finance_operations_histories', 'status') && !$this->indexExists('finance_operations_histories', 'idx_finance_user_status')) {
                    $table->index(['user_id', 'status'], 'idx_finance_user_status');
                }
                
                // Index for filtering by type
                if (Schema::hasColumn('finance_operations_histories', 'type') && !$this->indexExists('finance_operations_histories', 'idx_finance_type')) {
                    $table->index('type', 'idx_finance_type');
                }
                
                // Index for wallet lookups
                if (Schema::hasColumn('finance_operations_histories', 'wallet_id') && !$this->indexExists('finance_operations_histories', 'idx_finance_wallet_id')) {
                    $table->index('wallet_id', 'idx_finance_wallet_id');
                }
            });
        }

        // Products table indexes
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                // Composite index for category filtering with active status (if columns exist)
                if (Schema::hasColumn('products', 'category_id') && Schema::hasColumn('products', 'status') && !$this->indexExists('products', 'idx_products_category_status')) {
                    $table->index(['category_id', 'status'], 'idx_products_category_status');
                }
                
                // Index for active products filtering (check if status column exists)
                if (Schema::hasColumn('products', 'status') && !$this->indexExists('products', 'idx_products_status')) {
                    $table->index('status', 'idx_products_status');
                }
                
                // Alternative: Index for active boolean column
                if (Schema::hasColumn('products', 'active') && !$this->indexExists('products', 'idx_products_active')) {
                    $table->index('active', 'idx_products_active');
                }
                
                // Index for user_id if it exists
                if (Schema::hasColumn('products', 'user_id') && !$this->indexExists('products', 'idx_products_user_id')) {
                    $table->index('user_id', 'idx_products_user_id');
                }
            });
        }

        // Support requests indexes
        if (Schema::hasTable('support_requests')) {
            Schema::table('support_requests', function (Blueprint $table) {
                // Composite index for user support requests with status
                if (Schema::hasColumn('support_requests', 'user_id') && Schema::hasColumn('support_requests', 'status') && !$this->indexExists('support_requests', 'idx_support_user_status')) {
                    $table->index(['user_id', 'status'], 'idx_support_user_status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if ($this->indexExists('orders', 'idx_orders_user_customer_id')) {
                    $table->dropIndex('idx_orders_user_customer_id');
                }
                if ($this->indexExists('orders', 'idx_orders_user_seller_id')) {
                    $table->dropIndex('idx_orders_user_seller_id');
                }
                if ($this->indexExists('orders', 'idx_orders_status_created')) {
                    $table->dropIndex('idx_orders_status_created');
                }
                if ($this->indexExists('orders', 'idx_orders_user_id')) {
                    $table->dropIndex('idx_orders_user_id');
                }
            });
        }

        if (Schema::hasTable('finance_operations_histories')) {
            Schema::table('finance_operations_histories', function (Blueprint $table) {
                if ($this->indexExists('finance_operations_histories', 'idx_finance_user_status')) {
                    $table->dropIndex('idx_finance_user_status');
                }
                if ($this->indexExists('finance_operations_histories', 'idx_finance_type')) {
                    $table->dropIndex('idx_finance_type');
                }
                if ($this->indexExists('finance_operations_histories', 'idx_finance_wallet_id')) {
                    $table->dropIndex('idx_finance_wallet_id');
                }
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if ($this->indexExists('products', 'idx_products_category_status')) {
                    $table->dropIndex('idx_products_category_status');
                }
                if ($this->indexExists('products', 'idx_products_status')) {
                    $table->dropIndex('idx_products_status');
                }
                if ($this->indexExists('products', 'idx_products_active')) {
                    $table->dropIndex('idx_products_active');
                }
                if ($this->indexExists('products', 'idx_products_user_id')) {
                    $table->dropIndex('idx_products_user_id');
                }
            });
        }

        if (Schema::hasTable('support_requests')) {
            Schema::table('support_requests', function (Blueprint $table) {
                if ($this->indexExists('support_requests', 'idx_support_user_status')) {
                    $table->dropIndex('idx_support_user_status');
                }
            });
        }
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
