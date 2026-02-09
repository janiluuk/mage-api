<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('video_job_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('base_video_job_id')->index();
            $table->unsignedInteger('variant_video_job_id')->index();
            $table->unsignedBigInteger('model_id')->nullable()->index();
            $table->string('variant_name')->nullable();
            $table->text('description')->nullable();
            $table->integer('variant_order')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->index();
            $table->timestamps();

            // Foreign keys
            $table->foreign('base_video_job_id')
                ->references('id')->on('video_jobs')
                ->onDelete('cascade');
            
            $table->foreign('variant_video_job_id')
                ->references('id')->on('video_jobs')
                ->onDelete('cascade');
            
            $table->foreign('model_id')
                ->references('id')->on('model_files')
                ->onDelete('set null');

            // Ensure unique combinations
            $table->unique(['base_video_job_id', 'variant_video_job_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_job_variants');
    }
};
