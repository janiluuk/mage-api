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
     * in the video_jobs table. These indexes support the queue management system
     * and status filtering operations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('video_jobs', function (Blueprint $table) {
            // Composite index for queue processing queries
            // Supports WHERE status IN (...) ORDER BY queued_at, id
            if (!$this->indexExists('video_jobs', 'idx_video_jobs_status_queued')) {
                $table->index(['status', 'queued_at', 'id'], 'idx_video_jobs_status_queued');
            }
            
            // Composite index for model-specific statistics
            // Supports WHERE model_id = ? AND status = ? queries
            if (!$this->indexExists('video_jobs', 'idx_video_jobs_model_status')) {
                $table->index(['model_id', 'status'], 'idx_video_jobs_model_status');
            }
            
            // Index for updated_at to support stale job detection
            // Supports WHERE updated_at < ? queries for cleanup
            if (!$this->indexExists('video_jobs', 'idx_video_jobs_updated_at')) {
                $table->index('updated_at', 'idx_video_jobs_updated_at');
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
        Schema::table('video_jobs', function (Blueprint $table) {
            $table->dropIndex('idx_video_jobs_status_queued');
            $table->dropIndex('idx_video_jobs_model_status');
            $table->dropIndex('idx_video_jobs_updated_at');
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
