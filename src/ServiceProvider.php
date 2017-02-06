<?php
namespace DreamFactory\Core\Rws;

use DreamFactory\Core\Components\ServiceDocBuilder;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Rws\Models\RwsConfig;
use DreamFactory\Core\Rws\Services\RemoteWeb;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    use ServiceDocBuilder;

    public function boot()
    {
        // Add our service types.
        $this->app->resolving('df.service', function (ServiceManager $df) {
            $df->addType(
                new ServiceType([
                    'name'            => 'rws',
                    'label'           => 'HTTP Service',
                    'description'     => 'A service to handle Remote Web Services',
                    'group'           => ServiceTypeGroups::REMOTE,
                    'config_handler'  => RwsConfig::class,
                    'default_api_doc' => function ($service) {
                        return $this->buildServiceDoc($service->id, RemoteWeb::getApiDocInfo($service));
                    },
                    'factory'         => function ($config) {
                        return new RemoteWeb($config);
                    },
                ])
            );
        });

        // add migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
