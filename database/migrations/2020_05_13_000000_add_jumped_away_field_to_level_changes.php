<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJumpedAwayFieldToLevelChanges extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('level_changes', function (Blueprint $table) {
            $table->boolean('jumped_away')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('level_changes', function (Blueprint $table) {
            $table->dropColumn('jumped_away');
        });
    }
}
