<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_jobs', function (Blueprint $table) {
            $table->decimal('soundtrack_start_seconds', 10, 3)->nullable()->after('soundtrack_mimetype');
            $table->decimal('soundtrack_end_seconds', 10, 3)->nullable()->after('soundtrack_start_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('video_jobs', function (Blueprint $table) {
            $table->dropColumn(['soundtrack_start_seconds', 'soundtrack_end_seconds']);
        });
    }
};
