<?php
namespace DreamFactory\Core\Rws\Models;

use DreamFactory\Core\Models\BaseServiceConfigModel;

/**
 * HeaderConfig
 *
 * @property integer $id
 * @property string  $name
 * @property string  $value
 * @property boolean pass_from_client
 * @property integer $action
 * @method static \Illuminate\Database\Query\Builder|HeaderConfig whereId($value)
 * @method static \Illuminate\Database\Query\Builder|HeaderConfig whereName($value)
 * @method static \Illuminate\Database\Query\Builder|HeaderConfig whereServiceId($value)
 */
class HeaderConfig extends BaseServiceConfigModel
{
    protected $table = 'rws_headers_config';

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
                    'Which REST verbs should this header be applied to outgoing requests.';
                break;
        }
    }
}