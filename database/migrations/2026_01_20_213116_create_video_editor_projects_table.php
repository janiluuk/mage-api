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
        Schema::create('video_editor_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('state'); // Store editor state (timeline, fragments, settings)
            $table->string('video_type')->nullable(); // 'file' or 'job'
            $table->unsignedBigInteger('video_id')->nullable(); // Reference to original video
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('video_type');
            $table->index('video_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('video_editor_projects');
    }
};
