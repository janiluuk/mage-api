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
        Schema::table('generator_instances', function (Blueprint $table) {
            $table->string('current_model')->nullable()->after('processing_count');
            $table->integer('gpu_utilization')->nullable()->after('current_model');
            $table->integer('cpu_utilization')->nullable()->after('gpu_utilization');
            $table->integer('memory_utilization')->nullable()->after('cpu_utilization');
            $table->enum('health_status', ['online', 'offline', 'degraded'])->default('offline')->after('memory_utilization');
            $table->timestamp('last_health_check_at')->nullable()->after('health_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('generator_instances', function (Blueprint $table) {
            $table->dropColumn([
                'current_model',
                'gpu_utilization',
                'cpu_utilization',
                'memory_utilization',
                'health_status',
                'last_health_check_at',
            ]);
        });
    }
};

