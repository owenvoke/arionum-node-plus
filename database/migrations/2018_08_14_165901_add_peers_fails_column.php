<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class AddPeersFailsColumn
 */
class AddPeersFailsColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('peers', function (Blueprint $table) {
            $table->tinyInteger('fails')->after('ip')->default(0);
        });

        DB::table('config')->where('cfg', 'dbversion')->update(['val' => 6]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('peers', function (Blueprint $table) {
            $table->dropColumn('fails');
        });

        DB::table('config')->where('cfg', 'dbversion')->update(['val' => 5]);
    }
}
