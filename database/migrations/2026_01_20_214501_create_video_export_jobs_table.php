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
        Schema::create('video_export_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->json('fragments'); // Array of fragment data
            $table->json('input_files'); // Array of input file URLs and metadata
            $table->json('filter_graph'); // FFmpeg filter graph
            $table->json('outputs'); // Output stream identifiers
            $table->json('export_options'); // FPS, bitrate, resolution, etc.
            $table->string('output_name')->nullable();
            $table->string('output_path')->nullable(); // Path to exported file
            $table->string('output_url')->nullable(); // URL to exported file
            $table->decimal('progress', 5, 2)->default(0); // 0-100
            $table->string('timemark')->nullable(); // Current processing time
            $table->text('error')->nullable(); // Error message if failed
            $table->json('output_log')->nullable(); // FFmpeg output logs
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('video_export_jobs');
    }
};
