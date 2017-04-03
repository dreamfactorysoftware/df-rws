<?php
namespace DreamFactory\Core\Rws\Models;

use DreamFactory\Core\Models\BaseServiceConfigModel;

/**
 * ParameterConfig
 *
 * @property integer $id
 * @property integer $service_id
 * @property string  $name
 * @property string  $value
 * @property boolean $exclude
 * @property boolean $outbound
 * @property string  $cache_key
 * @property integer $action
 * @method static \Illuminate\Database\Query\Builder|ParameterConfig whereId($value)
 * @method static \Illuminate\Database\Query\Builder|ParameterConfig whereName($value)
 * @method static \Illuminate\Database\Query\Builder|ParameterConfig whereServiceId($value)
 */
class ParameterConfig extends BaseServiceConfigModel
{
    protected $table = 'rws_parameters_config';

    protected $fillable = ['service_id', 'name', 'value', 'exclude', 'outbound', 'cache_key', 'action'];

    protected $casts = [
        'exclude'    => 'boolean',
        'outbound'   => 'boolean',
        'cache_key'  => 'boolean',
        'id'         => 'integer',
        'service_id' => 'integer',
        'action'     => 'integer'
    ];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    public $incrementing = true;

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'action':
                $schema['label'] = 'Verbs';
                $schema['type'] = 'verb_mask';
                $schema['description'] =
                    'Which REST verbs should this parameter be applied to.';
                break;
        }
    }
}