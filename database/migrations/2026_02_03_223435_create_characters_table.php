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
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('traits')->nullable(); // Array of character traits
            $table->json('metadata')->nullable(); // Additional character data (appearance, backstory, etc.)
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'name']);
        });

        // Pivot table for film_project_character relationship
        Schema::create('film_project_character', function (Blueprint $table) {
            $table->id();
            $table->foreignId('film_project_id')->constrained('film_productions')->onDelete('cascade');
            $table->foreignId('character_id')->constrained()->onDelete('cascade');
            $table->string('role')->nullable(); // e.g., 'protagonist', 'antagonist', 'supporting'
            $table->timestamps();
            
            $table->unique(['film_project_id', 'character_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('film_project_character');
        Schema::dropIfExists('characters');
    }
};
