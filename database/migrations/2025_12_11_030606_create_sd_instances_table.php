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
        Schema::create('sd_instances', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->enum('type', ['stable_diffusion_forge', 'comfyui'])->default('stable_diffusion_forge');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sd_instances');
    }
};
