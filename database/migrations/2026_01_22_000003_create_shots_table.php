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
        Schema::create('shots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('film_production_id')->constrained('film_productions')->onDelete('cascade');
            $table->foreignId('sequence_id')->constrained('sequences')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('duration', 8, 2)->nullable(); // Duration in seconds
            $table->integer('order')->default(1);
            $table->json('scene_data')->nullable(); // AI-generated scene data (video URL, metadata, etc.)
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shots');
    }
};

