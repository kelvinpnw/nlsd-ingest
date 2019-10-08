<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIncidentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedInteger('reporter_id');
            $table->foreign('reporter_id')->references('id')->on('reporters')->comment('Corresponds with `id` in the reporter table');
            $table->string('sourceip', 48)->comment('Source IP Address, the aggressor of this incident.');
            $table->string('destinationip', 48)->comment('Destination IP Address, the IP of the server which reported this incident.');
            $table->string('protocol',6)->comment('protocol, probably TCP or UDP');
            $table->unsignedInteger('sourceport')->comment('The source port of this incident, likely an ephemeral port.');
            $table->string('destport')->comment('The destination port of this incident, the target of the attack.');
            $table->unsignedInteger('classification')->comment('Incident classification. Corresponds to a type of attack, port scan, persistent entry attempt, blacklist evasion.')->nullable();
            $table->boolean('reportfiled')->comment('Has a report been filed with other abuse handling platforms? True or false.');
            $table->text('line')->comment('the full log line');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('incidents');
    }
}
