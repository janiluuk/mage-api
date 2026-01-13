<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sd_instances') && !Schema::hasTable('generator_instances')) {
            Schema::rename('sd_instances', 'generator_instances');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('generator_instances') && !Schema::hasTable('sd_instances')) {
            Schema::rename('generator_instances', 'sd_instances');
        }
    }
};
