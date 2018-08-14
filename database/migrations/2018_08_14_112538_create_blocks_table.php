<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class CreateBlocksTable
 */
class CreateBlocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->string('id', 128)->primary();
            $table->string('generator', 128);
            $table->integer('height')->unique();
            $table->integer('date');
            $table->string('nonce', 128);
            $table->string('signature', 256);
            $table->string('difficulty', 64);
            $table->string('argon', 128);
            $table->integer('transactions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('blocks');
    }
}
