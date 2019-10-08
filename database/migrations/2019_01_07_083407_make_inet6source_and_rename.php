<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeInet6sourceAndRename extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('incidents', function ($table) {
            $table->dropColumn('inet_sourceip');
        });
        Schema::table('incidents', function ($table) {
            $table->unsignedInteger('inet_sourceip')->nullable();
        });
        DB::statement('ALTER TABLE `incidents` ADD `inet6_sourceip` VARBINARY(16)');
        DB::statement('UPDATE `incidents` SET `inet_sourceip` = INET_ATON(`sourceip`) WHERE IS_IPV4(`sourceip`)');
        DB::statement(' UPDATE `incidents` SET `inet6_sourceip` = INET6_ATON(`sourceip`) WHERE IS_IPV6(`sourceip`)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
