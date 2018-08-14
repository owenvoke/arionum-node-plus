<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class AddAccountsAliasIndex
 */
class AddAccountsAliasIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->index(['alias']);
        });

        DB::table('config')->where('cfg', 'dbversion')->update(['val' => 8]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['alias']);
        });

        DB::table('config')->where('cfg', 'dbversion')->update(['val' => 7]);
    }
}
