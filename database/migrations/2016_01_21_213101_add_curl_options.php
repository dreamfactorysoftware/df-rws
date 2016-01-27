<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

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
                $table->text('options')->nullable();
            });
    }

    /** @inheritdoc */
    public function down()
    {
        Schema::table('rws_config',
            function(Blueprint $table) {
                //  Drop the options column if it was there
                Schema::hasColumn('rws_config', 'options') && $table->dropColumn('options');
            });
    }
}
