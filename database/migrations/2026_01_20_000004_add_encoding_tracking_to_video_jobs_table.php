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
            $table->enum('encoding_status', ['pending', 'encoding', 'completed', 'failed'])->nullable()->after('status');
            $table->timestamp('encoding_started_at')->nullable()->after('encoding_status');
            $table->timestamp('encoding_completed_at')->nullable()->after('encoding_started_at');
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
            $table->dropColumn(['encoding_status', 'encoding_started_at', 'encoding_completed_at']);
        });
    }
};

