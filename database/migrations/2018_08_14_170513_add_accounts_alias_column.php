<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class AddAccountsAliasColumn
 */
class AddAccountsAliasColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('alias', 32)->after('balance')->nullable()->default(null);
        });

        DB::table('config')->where('cfg', 'dbversion')->update(['val' => 7]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('alias');
        });

        DB::table('config')->where('cfg', 'dbversion')->update(['val' => 6]);
    }
}
