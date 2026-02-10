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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'user_role_id')) {
                $afterColumn = Schema::hasColumn('users', 'balance') ? 'balance' : 'profile_image';
                $table->unsignedBigInteger('user_role_id')->nullable()->after($afterColumn);
                $table->foreign('user_role_id')->references('id')->on('user_roles')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'user_role_id')) {
                $table->dropForeign(['user_role_id']);
                $table->dropColumn('user_role_id');
            }
        });
    }
};
