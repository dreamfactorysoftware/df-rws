<?php
/**
 * This file is part of the DreamFactory(tm)
 *
 * DreamFactory(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use DreamFactory\Library\Utility\Enums\Verbs;

class RwsTest extends \DreamFactory\Core\Testing\TestCase
{
    protected static $staged = false;

    public function stage()
    {
        parent::stage();

        Artisan::call( 'migrate', ['--path' => 'vendor/dreamfactory/df-rws/database/migrations/'] );
        Artisan::call( 'db:seed', ['--class' => 'DreamFactory\\Core\\Rws\\Database\\Seeds\\DatabaseSeeder'] );

        if(!$this->serviceExists('gmap'))
        {
            \DreamFactory\Core\Models\Service::create(
                [
                    "name"=>"gmap",
                    "type"=>"rws",
                    "label"=>"Remote web service",
                    "config"=>[
                        "base_url"=>"http://maps.googleapis.com/maps/api/directions/json",
                        "cache_enabled"=>0,
                        "parameters"=>[
                            [
                                "name"=>"origin",
                                "value"=>"5965 Willow Oak Pass, Cumming, GA 30040",
                                "outbound"=>1,
                                "cache_key"=>1,
                                "action"=>31
                            ],
                            [
                                "name"=>"destination",
                                "value"=>"3600 Mansell Rd. Alpharetta, GA",
                                "outbound"=>1,
                                "cache_key"=>1,
                                "action"=>31
                            ]
                        ]
                    ]
                ]
            );
        }

        if(!$this->serviceExists('dsp-tester'))
        {
            \DreamFactory\Core\Models\Service::create(
                [
                    "name"=>"dsp-tester",
                    "type"=>"rws",
                    "label"=>"Remote web service",
                    "config"=>[
                        "base_url"=>"https://dsp-tester.cloud.dreamfactory.com/rest",
                        "cache_enabled"=>0,
                        "headers"=>[
                            [
                                "name"=>"Authorization",
                                "value"=>"Basic YXJpZmlzbGFtQGRyZWFtZmFjdG9yeS5jb206dGVzdCEyMzQ=",
                                "pass_from_client"=>0,
                                "action"=>31
                            ],
                            [
                                "name"=>"X-DreamFactory-Application-Name",
                                "value"=>"admin",
                                "pass_from_client"=>0,
                                "action"=>31
                            ],
                            [
                                "name"=>"X-HTTP-Method",
                                "pass_from_client"=>1,
                                "action"=>31
                            ]
                        ]
                    ]
                ]
            );
        }
    }

    public function testGETGmapDirection()
    {
        $rs = $this->call(Verbs::GET, $this->prefix.'/gmap');
        $this->assertContains('{"routes":[{"bounds":{"northeast":{"lat":34.1951083,"lng":-84.2175458}', $rs->getContent());
    }

    public function testGETheaders()
    {
        $rs = $this->call(Verbs::GET, $this->prefix.'/dsp-tester');
        $this->assertEquals('{"service":[{"name":"Database","api_name":"db"},{"name":"Email Service","api_name":"email"},{"name":"Local File Storage","api_name":"files"},{"name":"Local Portal Service","api_name":"portal"}]}', $rs->getContent());
    }
}