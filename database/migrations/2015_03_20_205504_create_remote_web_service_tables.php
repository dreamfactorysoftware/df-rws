<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRemoteWebServiceTables extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		//RWS config table
        Schema::create(
            'rws_config',
            function ( Blueprint $t )
            {
                $t->integer( 'service_id' )->unsigned()->primary();
                $t->foreign( 'service_id' )->references( 'id' )->on( 'services' )->onDelete( 'cascade' );
                $t->string( 'base_url' )->nullable();
                $t->longText( 'parameters' )->nullable();
                $t->longText( 'headers' )->nullable();
            }
        );
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		//RWS config table
        Schema::dropIfExists( 'rws_config' );
	}

}
