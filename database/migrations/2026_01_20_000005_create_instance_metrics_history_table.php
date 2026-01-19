<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('instance_metrics_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('generator_instances')->onDelete('cascade');
            $table->string('current_model')->nullable();
            $table->integer('gpu_utilization')->nullable();
            $table->integer('cpu_utilization')->nullable();
            $table->integer('memory_utilization')->nullable();
            $table->integer('queue_size')->default(0);
            $table->integer('processing_count')->default(0);
            $table->enum('health_status', ['online', 'offline', 'degraded'])->default('offline');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['instance_id', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('instance_metrics_history');
    }
};


