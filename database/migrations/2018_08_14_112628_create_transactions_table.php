<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class CreateTransactionsTable
 */
class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->string('id', 128)->primary();
            $table->string('block', 128);
            $table->integer('height');
            $table->string('dst', 128);
            $table->decimal('val', 20, 8);
            $table->decimal('fee', 20, 8);
            $table->string('signature', 256);
            $table->tinyInteger('version');
            $table->string('message', 256)->nullable()->default('');
            $table->integer('date');
            $table->string('public_key', 1024);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
