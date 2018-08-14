<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class CreateMempoolTable
 */
class CreateMempoolTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mempool', function (Blueprint $table) {
            $table->string('id', 128)->primary();
            $table->integer('height')->unique();
            $table->string('src', 128);
            $table->string('dst', 128);
            $table->decimal('val', 20, 8);
            $table->decimal('fee', 20, 8);
            $table->string('signature', 256);
            $table->tinyInteger('version');
            $table->string('message', 256)->nullable()->default('');
            $table->string('public_key', 1024);
            $table->bigInteger('date');
            $table->string('peer', 64)->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mempool');
    }
}
