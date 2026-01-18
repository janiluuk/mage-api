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
        if (Schema::hasTable('batch_video_job')) {
            Schema::table('batch_video_job', function (Blueprint $table) {
                if (!Schema::hasColumn('batch_video_job', 'description')) {
                    $table->text('description')->nullable()->after('order');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('batch_video_job')) {
            Schema::table('batch_video_job', function (Blueprint $table) {
                if (Schema::hasColumn('batch_video_job', 'description')) {
                    $table->dropColumn('description');
                }
            });
        }
    }
};

