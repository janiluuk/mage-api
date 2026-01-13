<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('model_files', function (Blueprint $table) {
            if (!Schema::hasColumn('model_files', 'instance_type')) {
                $table->string('instance_type')->nullable()->after('enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('model_files', function (Blueprint $table) {
            if (Schema::hasColumn('model_files', 'instance_type')) {
                $table->dropColumn('instance_type');
            }
        });
    }
};
