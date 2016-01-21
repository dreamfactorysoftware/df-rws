<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCurlOptions extends Migration
{
    //******************************************************************************
    //* Methods
    //******************************************************************************

    /** @inheritdoc */
    public function up()
    {
        Schema::table('rws_config',
            function(Blueprint $table) {
                $table->string('proxy', 256)->nullable();
                $table->string('proxy_credentials', 128)->nullable();
                $table->text('curl_options')->nullable();
            });
    }

    /** @inheritdoc */
    public function down()
    {
        Schema::table('rws_config',
            function(Blueprint $table) {
                //  Drop the curl_options column if it was there
                Schema::hasColumn('rws_config', 'proxy') && $table->dropColumn('proxy');
                Schema::hasColumn('rws_config', 'proxy_credentials') && $table->dropColumn('proxy_credentials');
                Schema::hasColumn('rws_config', 'curl_options') && $table->dropColumn('curl_options');
            });
    }
}
