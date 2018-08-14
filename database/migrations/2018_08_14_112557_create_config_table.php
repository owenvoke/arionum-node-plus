<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class CreateConfigTable
 */
class CreateConfigTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('config', function (Blueprint $table) {
            $table->string('cfg', 30)->primary();
            $table->string('val', 200);
        });

        DB::table('config')->insert([
            [
                'cfg'   => 'hostname',
                'val' => '',
            ],
            [
                'cfg'   => 'dbversion',
                'val' => 1,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('config');
    }
}
