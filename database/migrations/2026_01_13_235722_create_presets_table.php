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
        Schema::create('presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // e.g., 'vid2vid', 'deforum', 'general'
            $table->string('type')->default('video'); // video, image, animation
            $table->json('settings'); // All preset parameters
            $table->boolean('is_public')->default(false); // For sharing
            $table->boolean('is_favorite')->default(false);
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'category']);
            $table->index(['user_id', 'is_favorite']);
            $table->index('is_public');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('presets');
    }
};
