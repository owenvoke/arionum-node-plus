<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class CreateMasternodeTable
 */
class CreateMasternodeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('masternode', function (Blueprint $table) {
            $table->string('public_key', 128)->primary();
            $table->integer('height');
            $table->string('ip', 16);
            $table->integer('last_won')->default(0);
            $table->integer('blacklist')->default(0);
            $table->integer('fails')->default(0);
            $table->tinyInteger('status')->default(1);
        });

        DB::table('config')->where('cfg', 'dbversion')->update(['val' => 9]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('masternode');

        DB::table('config')->where('cfg', 'dbversion')->update(['val' => 8]);
    }
}
