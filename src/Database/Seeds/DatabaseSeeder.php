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
namespace DreamFactory\Rave\Rws\Database\Seeds;

use DreamFactory\Rave\Database\Seeds\BaseModelSeeder;

class DatabaseSeeder extends BaseModelSeeder
{
    protected $modelClass = 'DreamFactory\\Rave\\Models\\ServiceType';

    protected $records = [
        [
            'name'           => 'rws',
            'class_name'     => "DreamFactory\\Rave\\Rws\\Services\\RemoteWeb",
            'config_handler' => "DreamFactory\\Rave\\Rws\\Models\\RwsConfig",
            'label'          => 'Remote Web Service',
            'description'    => 'A rave service to handle Remote Web Services',
            'group'          => '',
            'singleton'      => 1
        ]
    ];
}