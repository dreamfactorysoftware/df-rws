<?php
namespace DreamFactory\Core\Rws\Database\Seeds;

use DreamFactory\Core\Database\Seeds\BaseModelSeeder;

class DatabaseSeeder extends BaseModelSeeder
{
    protected $modelClass = 'DreamFactory\\Core\\Models\\ServiceType';

    protected $records = [
        [
            'name'           => 'rws',
            'class_name'     => "DreamFactory\\Core\\Rws\\Services\\RemoteWeb",
            'config_handler' => "DreamFactory\\Core\\Rws\\Models\\RwsConfig",
            'label'          => 'Remote Web Service',
            'description'    => 'A service to handle Remote Web Services',
            'group'          => '',
            'singleton'      => 1
        ]
    ];
}