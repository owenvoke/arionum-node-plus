<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Class AddForeignKeys
 */
class AddForeignKeys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreign('block')->references('id')->on('blocks')->onDelete('cascade');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('block')->references('id')->on('blocks')->onDelete('cascade');
        });

        DB::table('config')->where('cfg', 'dbversion')->update(['val' => 1]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign('accounts_block_foreign');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign('transactions_block_foreign');
        });

        DB::table('config')->where('cfg', 'dbversion')->update(['val' => 0]);
    }
}
