<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStoryFramesTable extends Migration
{
    public function up()
    {
        Schema::create('story_frames', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('story_batch_id')->constrained('story_batches', 'id')->onDelete('cascade');
            $table->integer('frame_id');
            $table->text('prompt')->nullable();
            $table->string('image_url')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('story_frames');
    }
}

