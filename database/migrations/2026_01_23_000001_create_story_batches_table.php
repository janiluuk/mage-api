<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStoryBatchesTable extends Migration
{
    public function up()
    {
        Schema::create('story_batches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users', 'id');
            $table->string('status')->default('pending');
            $table->integer('progress')->default(0);
            $table->integer('total_frames')->default(0);
            $table->integer('completed_frames')->default(0);
            $table->longText('config_json')->nullable();
            $table->string('share_token')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('story_batches');
    }
}

