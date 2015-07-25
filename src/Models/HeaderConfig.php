<?php
namespace DreamFactory\Core\Rws\Models;

use DreamFactory\Core\Models\BaseModel;

class HeaderConfig extends BaseModel
{
    protected $table = 'rws_headers_config';

    protected $primaryKey = 'id';

    protected $fillable = ['service_id', 'name', 'value', 'pass_from_client', 'action'];

    protected $casts = [
        'pass_from_client' => 'boolean',
        'id'               => 'integer',
        'service_id'       => 'integer',
        'action'           => 'integer'
    ];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    public $incrementing = true;
}