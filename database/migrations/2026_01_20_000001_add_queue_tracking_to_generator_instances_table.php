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
            $table->integer('queue_size')->default(0)->after('enabled');
            $table->integer('processing_count')->default(0)->after('queue_size');
            $table->timestamp('last_queue_check_at')->nullable()->after('processing_count');
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
            $table->dropColumn(['queue_size', 'processing_count', 'last_queue_check_at']);
        });
    }
};

