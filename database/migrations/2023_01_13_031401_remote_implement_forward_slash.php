<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoteImplementForwardSlash extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rws_config', function (Blueprint $table) {
            $table->boolean('preserve_forward_trailing_slash')->default(0)->after('implements_access_list');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('rws_config', function (Blueprint $table) {
            $table->dropColumn('preserve_forward_trailing_slash');
        });
    }
}
