<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePortsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ports', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('destport')->comment('port number');
            $table->string('protocol',8)->comment('protocol');
            $table->string('flag',32)->comment('short, recognizable string to identify the type of incident.');
            $table->text('description')->comment('Text describing the incident type.');
            $table->boolean('isIoTTarget')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ports');
    }
}
