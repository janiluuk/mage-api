<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMediaManagerLockListTable extends Migration
{
    /**
     * Run the migrations.
     * 
     * Note: This migration is for the abandoned ctf0/media-manager package.
     * The table is no longer used but kept for historical migration compatibility.
     */
    public function up()
    {
        $tableName = 'locked'; // Default table name (mediaManager config no longer exists)
        
        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('path');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('locked');
    }
}
