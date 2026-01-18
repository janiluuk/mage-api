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
        Schema::create('instance_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('generator_instances')->onDelete('cascade');
            $table->foreignId('video_job_id')->constrained('video_jobs')->onDelete('cascade');
            $table->string('status')->default('queued'); // queued, processing, completed, failed, cancelled
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('processing_time_seconds')->nullable(); // Time taken to process in seconds
            $table->timestamps();

            $table->index(['instance_id', 'status']);
            $table->index(['video_job_id']);
            $table->index(['status', 'assigned_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('instance_jobs');
    }
};

