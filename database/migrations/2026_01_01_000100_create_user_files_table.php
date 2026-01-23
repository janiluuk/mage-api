<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('project_id')->nullable();
            $table->string('original_name');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->unsignedBigInteger('size');
            $table->string('mime_type')->nullable();
            $table->string('type')->default('file');
            $table->string('variant')->nullable();
            $table->foreignId('parent_file_id')->nullable()->constrained('user_files')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_files');
    }
};
