<?php
namespace DreamFactory\Core\Rws\Models;

use DreamFactory\Core\Models\BaseModel;

class ParameterConfig extends BaseModel
{
    protected $table = 'rws_parameters_config';

    protected $primaryKey = 'id';

    protected $fillable = ['service_id', 'name', 'value', 'exclude', 'outbound', 'cache_key', 'action'];

    protected $casts = ['exclude' => 'boolean', 'outbound' => 'boolean', 'cache_key' => 'boolean'];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    public $incrementing = true;
}