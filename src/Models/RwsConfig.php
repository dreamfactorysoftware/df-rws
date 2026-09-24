<?php namespace DreamFactory\Core\Rws\Models;

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Models\BaseServiceConfigModel;
use DreamFactory\Core\Models\ServiceCacheConfig;

class RwsConfig extends BaseServiceConfigModel
{
    protected $table = 'rws_config';

    protected $fillable = [
        'service_id',
        'base_url',
        'options',
        'replace_link',
        'implements_access_list',
        'preserve_forward_trailing_slash',
        'oauth_token_url',
        'oauth_client_id',
        'oauth_client_secret',
        'oauth_grant_type',
        'oauth_scope',
        'oauth_auth_method',
    ];

    protected $casts = [
        'options' => 'array',
        'service_id' => 'integer',
        'replace_link' => 'boolean',
        'implements_access_list' => 'boolean',
        'preserve_forward_trailing_slash' => 'boolean',
    ];

    protected $encrypted = ['oauth_client_secret'];

    protected $protected = ['oauth_client_secret'];

    /**
     * {@inheritdoc}
     */
    public static function getConfig($id, $local_config = null, $protect = true)
    {
        $config = parent::getConfig($id, $local_config, $protect);

        $params = ParameterConfig::whereServiceId($id)->get();
        $items = [];
        /** @var ParameterConfig $param */
        foreach ($params as $param) {
            $param->protectedView = $protect;
            $items[] = $param->toArray();
        }
        $config['parameters'] = $items;
        $headers = HeaderConfig::whereServiceId($id)->get();
        $items = [];
        /** @var HeaderConfig $header */
        foreach ($headers as $header) {
            $header->protectedView = $protect;
            $items[] = $header->toArray();
        }
        $config['headers'] = $items;
        $cacheConfig = ServiceCacheConfig::whereServiceId($id)->first();
        $config = array_merge($config, ($cacheConfig ? $cacheConfig->toArray() : []));

        return $config;
    }

    /**
     * {@inheritdoc}
     */
    public static function setConfig($id, $config, $local_config = null)
    {
        if (isset($config['parameters'])) {
            $params = $config['parameters'];
            if (!is_array($params)) {
                throw new BadRequestException('Web service parameters must be an array.');
            }
            ParameterConfig::whereServiceId($id)->delete();
            foreach ($params as $param) {
                ParameterConfig::setConfig($id, $param, $local_config);
            }
        }

        if (isset($config['headers'])) {
            $headers = $config['headers'];
            if (!is_array($headers)) {
                throw new BadRequestException('Web service headers must be an array.');
            }
            HeaderConfig::whereServiceId($id)->delete();
            foreach ($headers as $header) {
                HeaderConfig::setConfig($id, $header, $local_config);
            }
        }

        ServiceCacheConfig::setConfig($id, $config, $local_config);

        return parent::setConfig($id, $config, $local_config);
    }

    /**
     * {@inheritdoc}
     */
    public static function storeConfig($id, $config)
    {
        if (isset($config['parameters'])) {
            $params = $config['parameters'];
            if (!is_array($params)) {
                throw new BadRequestException('Web service parameters must be an array.');
            }
            ParameterConfig::whereServiceId($id)->delete();
            foreach ($params as $param) {
                ParameterConfig::storeConfig($id, $param);
            }
        }

        if (isset($config['headers'])) {
            $headers = $config['headers'];
            if (!is_array($headers)) {
                throw new BadRequestException('Web service headers must be an array.');
            }
            HeaderConfig::whereServiceId($id)->delete();
            foreach ($headers as $header) {
                HeaderConfig::storeConfig($id, $header);
            }
        }

        ServiceCacheConfig::storeConfig($id, $config);

        parent::storeConfig($id, $config);
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        $schema = parent::getConfigSchema();
        $schema[] = [
            'name'        => 'parameters',
            'label'       => 'Parameters',
            'description' => 'Supply additional parameters to pass to the remote service, or exclude parameters passed from client.',
            'type'        => 'array',
            'required'    => false,
            'allow_null'  => true,
            'items'       => ParameterConfig::getConfigSchema()
        ];
        $schema[] = [
            'name'        => 'headers',
            'label'       => 'Headers',
            'description' => 'Supply additional headers to pass to the remote service.',
            'type'        => 'array',
            'required'    => false,
            'allow_null'  => true,
            'items'       => HeaderConfig::getConfigSchema()
        ];
        $schema = array_merge($schema, ServiceCacheConfig::getConfigSchema());

        return $schema;
    }

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'base_url':
                $schema['label'] = 'Base URL (required if not defined in Service Definition)';
                $schema['type'] = 'text';
                $schema['required'] = false;
                $schema['description'] = 'This is the root for the external call, ' .
                    'additional resource path and parameters from client, ' .
                    'along with provisioned parameters and headers, will be added.';
                break;

            case 'options':
                $schema['label'] = 'CURL Options';
                $schema['type'] = 'object';
                $schema['object'] =
                    [
                        'key'   => ['label' => 'Name', 'type' => 'string'],
                        'value' => ['label' => 'Value', 'type' => 'string']
                    ];
                $schema['description'] =
                    'This contains any additional CURL settings to use when making remote web service requests, ' .
                    'described as CUROPT_XXX at http://php.net/manual/en/function.curl-setopt.php. ' .
                    'Notable options include PROXY and PROXYUSERPWD for getting calls through proxies.';
                break;
            case 'replace_link':
                $schema['label'] = 'Replace Hyperlinks';
                $schema['description'] =
                    'Replace external hyperlinks in response with DF equivalent hyperlinks when possible.';
                break;
            case 'implements_access_list':
                $schema['label'] = 'Remote Implements Access List';
                $schema['description'] =
                    'By default, the access list is generated by the OpenAPI specification provided. ' .
                    'To override, check this and implement the access list on the remote service.';
                break;
            case 'preserve_forward_trailing_slash':
                $schema['label'] = 'Preserve or add a forward trailing slash to your url';
                $schema['description'] =
                    "Enable this option if you want to add (or preserve) a trailing forward slash '/' to your url. ".
                    "Example: 'https://google.com' will become 'https://google.com/'";
                break;
            case 'oauth_token_url':
                $schema['label'] = 'OAuth2 Token URL';
                $schema['type'] = 'text';
                $schema['description'] =
                    'Token endpoint for OAuth2 client credentials grant. '.
                    'When set, DreamFactory auto-fetches bearer tokens before proxying requests. '.
                    'Leave blank to disable backend OAuth2.';
                break;
            case 'oauth_client_id':
                $schema['label'] = 'OAuth2 Client ID';
                $schema['type'] = 'text';
                $schema['description'] = 'OAuth2 client identifier.';
                break;
            case 'oauth_client_secret':
                $schema['label'] = 'OAuth2 Client Secret';
                $schema['type'] = 'password';
                $schema['description'] = 'OAuth2 client secret. Stored encrypted at rest.';
                break;
            case 'oauth_grant_type':
                $schema['label'] = 'OAuth2 Grant Type';
                $schema['type'] = 'picklist';
                $schema['values'] = [
                    ['label' => 'Client Credentials', 'name' => 'client_credentials'],
                ];
                $schema['default'] = 'client_credentials';
                $schema['description'] =
                    'Grant type used to obtain the backend access token. '.
                    'Client credentials is the machine-to-machine flow (no user context).';
                break;
            case 'oauth_scope':
                $schema['label'] = 'OAuth2 Scope';
                $schema['type'] = 'text';
                $schema['description'] =
                    'Optional OAuth2 scope. Required by some providers (e.g. Oracle OIDS/IDCS). '.
                    'Example: "ords/fdny/read" or "urn:opc:resource:consumer::all".';
                break;
            case 'oauth_auth_method':
                $schema['label'] = 'OAuth2 Client Auth Method';
                $schema['type'] = 'picklist';
                $schema['values'] = [
                    ['label' => 'Basic Auth Header (Oracle, default)', 'name' => 'basic_header'],
                    ['label' => 'POST Body (RFC 6749)', 'name' => 'post_body'],
                ];
                $schema['default'] = 'basic_header';
                $schema['description'] =
                    'How to send client credentials to the token endpoint. '.
                    'Oracle OIDS requires Basic auth header; some providers only accept POST body params.';
                break;
        }
    }
}
