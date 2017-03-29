<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddReplaceLinkField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('rws_config', 'replace_link')) {
            Schema::table(
                'rws_config',
                function (Blueprint $t){
                    $t->boolean('replace_link')->default(0)->after('base_url');
                }
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('rws_config', 'replace_link')) {
            Schema::table(
                'rws_config',
                function (Blueprint $t){
                    $t->dropColumn('replace_link');
                }
            );
        }
    }
}
