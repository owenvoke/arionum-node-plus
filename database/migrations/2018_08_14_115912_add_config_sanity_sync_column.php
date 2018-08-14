<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Class AddConfigSanitySyncColumn
 */
class AddConfigSanitySyncColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('config')->insert(['cfg' => 'sanity_sync', 'val' => 0]);

        DB::table('config')->where('cfg', 'dbversion')->update(['val' => 3]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('config')->where('cfg', 'sanity_sync')->delete();

        DB::table('config')->where('cfg', 'dbversion')->update(['val' => 2]);
    }
}
