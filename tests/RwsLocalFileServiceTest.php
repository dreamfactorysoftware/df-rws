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

class RwsLocalFileServiceTest extends \DreamFactory\Core\Testing\FileServiceTestCase
{
    protected static $staged = false;


    public function stage()
    {
        parent::stage();

        Artisan::call( 'migrate', ['--path' => 'vendor/dreamfactory/df-rws/database/migrations/'] );
        Artisan::call( 'db:seed', ['--class' => 'DreamFactory\\Core\\Rws\\Database\\Seeds\\DatabaseSeeder'] );

        if(!$this->serviceExists('rave'))
        {
            \DreamFactory\Core\Models\Service::create(
                [
                    "name"=>"rave",
                    "type"=>"rws",
                    "label"=>"Remote web service",
                    "config"=>[
                        "base_url"=>"http://df.local/rest",
                        "cache_enabled"=>0
                    ]
                ]
            );
        }
    }

    protected function setService()
    {
        $this->service = 'rave/files';
        $this->prefix = $this->prefix.'/'.$this->service;
    }
}