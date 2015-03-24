<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
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

class RwsTest extends \DreamFactory\Rave\Testing\TestCase
{
    protected static $staged = false;

    public function stage()
    {
        parent::stage();

        Artisan::call( 'migrate', ['--path' => 'vendor/dreamfactory/rave-rws/database/migrations/'] );
        Artisan::call( 'db:seed', ['--class' => 'DreamFactory\\Rave\\Rws\\Database\\Seeds\\DatabaseSeeder'] );

        if(!$this->serviceExists('gmap'))
        {
            \DreamFactory\Rave\Models\Service::create(
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
    }

    public function testGETGmapDirection()
    {
        $rs = $this->call(Verbs::GET, $this->prefix.'/gmap');
        $this->assertContains('{"routes":[{"bounds":{"northeast":{"lat":34.1951083,"lng":-84.2175458}', $rs->getContent());
    }
}