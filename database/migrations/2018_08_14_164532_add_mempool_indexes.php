<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class AddMempoolIndexes
 */
class AddMempoolIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('mempool', function (Blueprint $table) {
            $table->index(['src', 'peer', 'val']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('mempool', function (Blueprint $table) {
            $table->dropIndex(['src', 'peer', 'val']);
        });
    }
}
