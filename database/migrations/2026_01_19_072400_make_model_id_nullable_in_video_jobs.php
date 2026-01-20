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
        Schema::table('video_jobs', function (Blueprint $table) {
            // Make model_id nullable for custom jobs that don't use models
            $table->unsignedBigInteger('model_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('video_jobs', function (Blueprint $table) {
            $table->unsignedBigInteger('model_id')->nullable(false)->change();
        });
    }
};
