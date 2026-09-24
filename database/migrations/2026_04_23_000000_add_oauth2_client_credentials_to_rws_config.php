<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOauth2ClientCredentialsToRwsConfig extends Migration
{
    public function up()
    {
        Schema::table('rws_config', function (Blueprint $table) {
            $table->string('oauth_token_url')->nullable()->after('preserve_forward_trailing_slash');
            $table->string('oauth_client_id')->nullable()->after('oauth_token_url');
            $table->text('oauth_client_secret')->nullable()->after('oauth_client_id');
            $table->string('oauth_grant_type')->nullable()->after('oauth_client_secret');
            $table->string('oauth_scope')->nullable()->after('oauth_grant_type');
            $table->string('oauth_auth_method')->nullable()->after('oauth_scope');
        });
    }

    public function down()
    {
        Schema::table('rws_config', function (Blueprint $table) {
            $table->dropColumn([
                'oauth_token_url',
                'oauth_client_id',
                'oauth_client_secret',
                'oauth_grant_type',
                'oauth_scope',
                'oauth_auth_method',
            ]);
        });
    }
}
