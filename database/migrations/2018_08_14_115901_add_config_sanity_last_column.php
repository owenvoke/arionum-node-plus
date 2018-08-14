<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Class AddConfigSanityLastColumn
 */
class AddConfigSanityLastColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('config')->insert(['cfg' => 'sanity_last', 'val' => 0]);

        DB::table('config')->where('cfg', 'dbversion')->update(['val' => 2]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('config')->where('cfg', 'sanity_last')->delete();

        DB::table('config')->where('cfg', 'dbversion')->update(['val' => 1]);
    }
}
