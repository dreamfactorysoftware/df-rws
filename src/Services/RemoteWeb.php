<?php namespace DreamFactory\Core\Rws\Services;

use Config;
use DreamFactory\Core\Components\Cacheable;
use DreamFactory\Core\Contracts\CachedInterface;
use DreamFactory\Core\Contracts\HttpStatusCodeInterface;
use DreamFactory\Core\Enums\HttpStatusCodes;
use DreamFactory\Core\Enums\VerbsMask;
use DreamFactory\Core\Events\ResourcePostProcess;
use DreamFactory\Core\Events\ResourcePreProcess;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\Curl;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\Scalar;
use Log;

class RemoteWeb extends BaseRestService implements CachedInterface
{
    use Cacheable;

    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var string
     */
    protected $baseUrl;
    /**
     * @var array
     */
    protected $headers;
    /**
     * @var array
     */
    protected $parameters;
    /**
     * @type bool
     */
    protected $cacheEnabled = false;
    /**
     * @var string
     */
    protected $cacheQuery;
    /**
     * @var string
     */
    protected $query;
    /**
     * @var string
     */
    protected $url;
    /**
     * @var array
     */
    protected $options = [];
    /**
     * @var array
     */
    protected $apiDoc = [];

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Create a new RemoteWebService
     *
     * @param array $settings settings array
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($settings)
    {
        parent::__construct($settings);
        $this->autoDispatch = false;

        $config = (array)array_get($settings, 'config', []);
        $this->baseUrl = array_get($config, 'base_url');
        $this->options = (array)array_get($config, 'options');

        // Validate url setup
        if (empty($this->baseUrl)) {
            throw new \InvalidArgumentException('Remote Web Service base url can not be empty.');
        }
        $this->parameters = (array)array_get($config, 'parameters', []);
        $this->headers = (array)array_get($config, 'headers', []);

        $this->cacheEnabled = Scalar::boolval(array_get($config, 'cache_enabled'));
        $this->cacheTTL = intval(array_get($config, 'cache_ttl', Config::get('df.default_cache_ttl')));
        $this->cachePrefix = 'service_' . $this->id . ':';

        $this->apiDoc = (array)array_get($settings, 'doc');
    }

    /**
     * @param      $query
     * @param      $key
     * @param      $name
     * @param      $value
     * @param bool $add_to_query
     * @param bool $add_to_key
     */
    protected static function parseArrayParameter(
        &$query,
        &$key,
        $name,
        $value,
        $add_to_query = true,
        $add_to_key = true
    ){
        if (is_array($value)) {
            foreach ($value as $sub => $subValue) {
                static::parseArrayParameter($query,
                    $cache_key,
                    $name . '[' . $sub . ']',
                    $subValue,
                    $add_to_query,
                    $add_to_key);
            }
        } else {
            Session::replaceLookups($value, true);
            $part = urlencode($name);
            if (!empty($value)) {
                $part .= '=' . urlencode($value);
            }
            if ($add_to_query) {
                if (!empty($query)) {
                    $query .= '&';
                }
                $query .= $part;
            }
            if ($add_to_key) {
                if (!empty($key)) {
                    $key .= '&';
                }
                $key .= $part;
            }
        }
    }

    /**
     * @param $config
     * @param $action
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    protected static function doesActionApply($config, $action)
    {
        $excludeVerbMasks = intval(array_get($config, 'action'));
        $myActionMask = VerbsMask::toNumeric($action);

        return ($excludeVerbMasks & $myActionMask);
    }

    /**
     * @param array  $parameters
     * @param string $action
     * @param string $query
     * @param string $cache_key
     * @param array  $requestQuery
     *
     * @return void
     */
    protected static function buildParameterString($parameters, $action, &$query, &$cache_key, $requestQuery)
    {
        // inbound parameters from request to be passed on
        foreach ($requestQuery as $name => $value) {
            $outbound = true;
            $addToCacheKey = true;
            // unless excluded
            if (!empty($parameters)) {
                foreach ($parameters as $param) {
                    if (Scalar::boolval(array_get($param, 'exclude'))) {
                        if (0 === strcasecmp($name, strval(array_get($param, 'name')))) {
                            if (static::doesActionApply($param, $action)) {
                                $outbound = !Scalar::boolval(array_get($param, 'outbound', true));
                                $addToCacheKey = !Scalar::boolval(array_get($param, 'cache_key', true));
                            }
                        }
                    }
                }
            }

            static::parseArrayParameter($query, $cache_key, $name, $value, $outbound, $addToCacheKey);
        }

        // DSP additional outbound parameters
        if (!empty($parameters)) {
            foreach ($parameters as $param) {
                if (!Scalar::boolval(array_get($param, 'exclude'))) {
                    if (static::doesActionApply($param, $action)) {
                        $name = array_get($param, 'name');
                        $value = array_get($param, 'value');
                        $outbound = Scalar::boolval(array_get($param, 'outbound', true));
                        $addToCacheKey = Scalar::boolval(array_get($param, 'cache_key', true));

                        static::parseArrayParameter($query, $cache_key, $name, $value, $outbound, $addToCacheKey);
                    }
                }
            }
        }
    }

    /**
     * @param array  $headers
     * @param string $action
     * @param array  $options
     *
     * @return void
     */
    protected static function addHeaders($headers, $action, &$options)
    {
        if (null === array_get($options, CURLOPT_HTTPHEADER)) {
            $options[CURLOPT_HTTPHEADER] = [];
        }

        // DSP outbound headers, additional and pass through
        if (!empty($headers)) {
            foreach ($headers as $header) {
                if (is_array($header) && static::doesActionApply($header, $action)) {
                    $name = array_get($header, 'name');
                    $value = array_get($header, 'value');
                    if (Scalar::boolval(array_get($header, 'pass_from_client'))) {
                        // Check for Basic Auth pulled into server variable already
                        if ((0 === strcasecmp($name, 'Authorization')) &&
                            (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']))
                        ) {
                            $value =
                                'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW']);
                        } else {
                            $phpHeaderName = strtoupper(str_replace(['-', ' '], ['_', '_'], $name));
                            // check for non-standard headers (prefix HTTP_) and standard headers like Content-Type
                            if (isset($_SERVER['HTTP_' . $phpHeaderName])) {
                                $value = $_SERVER['HTTP_' . $phpHeaderName];
                            } elseif (isset($_SERVER[$phpHeaderName])) {
                                $value = $_SERVER[$phpHeaderName];
                            }
                        }
                    }
                    Session::replaceLookups($value, true);
                    $options[CURLOPT_HTTPHEADER][] = $name . ': ' . $value;
                }
            }
        }
    }

    /**
     * @throws \DreamFactory\Core\Exceptions\RestException
     * @return bool
     */
    protected function processRequest()
    {
        $query = '';
        $cacheQuery = '';

        //  set outbound parameters
        $this->buildParameterString($this->parameters, $this->action, $query, $cacheQuery,
            $this->request->getParameters());

        //	set outbound headers
        $this->addHeaders($this->headers, $this->action, $this->options);

        $data = $this->request->getContent();

        $resource = array_map('rawurlencode', $this->resourceArray);
        if (!empty($resource)) {
            $this->url = rtrim($this->baseUrl, '/') . '/' . implode('/',$resource);
        } else {
            $this->url = $this->baseUrl;
        }

        if (!empty($query)) {
            $splicer = (false === strpos($this->baseUrl, '?')) ? '?' : '&';
            $this->url .= $splicer . $query;
        }

        $cacheKey = '';
        if ($this->cacheEnabled) {
            switch ($this->action) {
                case Verbs::GET:
                    // build cache_key
                    $cacheKey = $this->action . ':' . $this->name;
                    if ($resource) {
                        $cacheKey .= ':' . $resource;
                    }
                    if (!empty($cacheQuery)) {
                        $cacheKey .= ':' . $cacheQuery;
                    }
                    $cacheKey = hash('sha256', $cacheKey);

                    if (null !== $result = $this->getFromCache($cacheKey)) {
                        return $result;
                    }
                    break;
            }
        }

        Log::debug('Outbound HTTP request: ' . $this->action . ': ' . $this->url);

        /**
         * 2016-01-21 GHA
         * Add support for proxying remote web service request using configurable CURL options
         */
        if (!empty($this->options)) {
            $options = [];

            foreach ($this->options as $key => $value) {
                if (!is_numeric($key)) {
                    if (defined($key)) {
                        $options[constant($key)] = $value;
                    } else {
                        throw new InternalServerErrorException("Invalid configuration: $key is not a defined option.");
                    }
                } else {
                    $options[$key] = $value;
                }
            }

            $this->options = $options;
            unset($options);
        }

        Curl::setDecodeToArray(true);
        $result = Curl::request($this->action, $this->url, $data, $this->options);

        if (false === $result) {
            $error = Curl::getError();
            $code = array_get($error, 'code', 500);
            $status = $code;
            //  In case the status code is not a valid HTTP Status code
            if (!in_array($status, HttpStatusCodes::getDefinedConstants())) {
                //  Do necessary translation here. Default is Internal server error.
                $status = HttpStatusCodeInterface::HTTP_INTERNAL_SERVER_ERROR;
            }

            throw new RestException($status, array_get($error, 'message'), $code);
        }

        $status = Curl::getLastHttpCode();
        if ($status >= 300) {
            if (!is_string($result)) {
                $result = json_encode($result);
            }

            throw new RestException($status, $result, $status);
        }

        $contentType = Curl::getInfo('content_type');
        $response = ResponseFactory::create($result, $contentType, $status);

        if ($this->cacheEnabled) {
            switch ($this->action) {
                case Verbs::GET:
                    $this->addToCache($cacheKey, $result);
                    break;
            }
        }

        return $response;
    }

    /**
     * Runs pre process tasks/scripts
     */
    protected function preProcess()
    {
        if (!empty($this->resourcePath)) {
            $events = array_keys((array)array_get($this->apiDoc, 'paths'));
            if (null !== $match = static::matchEventPath($events, $this->resourcePath)) {
                /** @noinspection PhpUnusedLocalVariableInspection */
                $results =
                    \Event::fire(new ResourcePreProcess($this->name, $match['path'], $this->request,
                        $match['resource']));
            }
        } else {
            parent::preProcess();
        }
    }

    /**
     * Runs post process tasks/scripts
     */
    protected function postProcess()
    {
        if (!empty($this->resourcePath)) {
            $events = array_keys((array)array_get($this->apiDoc, 'paths'));
            if (null !== $match = static::matchEventPath($events, $this->resourcePath)) {
                /** @noinspection PhpUnusedLocalVariableInspection */
                $results =
                    \Event::fire(new ResourcePostProcess($this->name, $match['path'], $this->request, $this->response,
                        $match['resource']));
            }
        } else {
            parent::postProcess();
        }
    }

    protected static function matchEventPath($paths, $search)
    {
        $pieces = explode('/', $search);
        foreach ($paths as $path) {
            // drop service from path
            $path = trim(strstr(trim($path, '/'), '/'), '/');
            $pathPieces = explode('/', $path);
            if (count($pieces) === count($pathPieces)) {
                if (empty($diffs = array_diff($pathPieces, $pieces))) {
                    return ['path' => $path, 'resource' => null];
                }

                $resources = [];
                foreach ($diffs as $ndx => $diff) {
                    if (0 !== strpos($diff, '{')) {
                        // not a replacement parameters, see if another path works
                        continue 2;
                    }

                    $resources[$diff] = $pieces[$ndx];
                }

                return ['path' => str_replace('/', '.', trim($path, '/')), 'resource' => $resources];
            }
        }

        return null;
    }

    /** @inheritdoc */
    public function getApiDocInfo()
    {
        return (!empty($this->apiDoc) ? $this->apiDoc : ['paths' => [], 'definitions' => []]);
    }
}
