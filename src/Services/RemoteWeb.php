<?php namespace DreamFactory\Core\Rws\Services;

use Config;
use DreamFactory\Core\Contracts\HttpStatusCodeInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Enums\HttpStatusCodes;
use DreamFactory\Core\Enums\VerbsMask;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Http\Controllers\StatusController;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Utility\Curl;
use DreamFactory\Core\Enums\Verbs;
use Log;

class RemoteWeb extends BaseRestService
{
    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var string
     */
    protected $baseUrl;
    /**
     * @var boolean
     */
    protected $replaceLinks = false;
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
     * @var array
     */
    protected $options = [];
    /**
     * @type bool
     */
    protected $implementsAccessList = false;

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

        $this->baseUrl = array_get($this->config, 'base_url');
        $this->replaceLinks = array_get($this->config, 'replace_link', false);
        // Validate url setup
        if (empty($this->baseUrl)) {
            // fancy trick to grab the base url from swagger
            $doc = $this->getApiDoc();
            if (!empty($doc) && !empty($host = array_get($doc, 'host'))) {
                if (!empty($protocol = array_get($doc, 'schemes'))) {
                    $protocol = (is_array($protocol) ? current($protocol) : $protocol);
                } else {
                    $protocol = 'http';
                }
                $basePath = array_get($doc, 'basePath', '');
                $this->baseUrl = $protocol . '://' . $host . $basePath;
            }
            if (empty($this->baseUrl)) {
                throw new \InvalidArgumentException('Remote Web Service base url can not be empty.');
            }
        }

        $this->options = (array)array_get($this->config, 'options');
        static::cleanOptions($this->options);
        $this->parameters = (array)array_get($this->config, 'parameters', []);
        $this->headers = (array)array_get($this->config, 'headers', []);

        $this->cacheEnabled = array_get_bool($this->config, 'cache_enabled');
        $this->cacheTTL = intval(array_get($this->config, 'cache_ttl', Config::get('df.default_cache_ttl')));

        $this->implementsAccessList = boolval(array_get($this->config, 'implements_access_list', false));
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
    ) {
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
     * @param array  $request_params
     *
     * @return void
     */
    protected static function buildParameterString($parameters, $action, &$query, &$cache_key, $request_params = [])
    {
        // Using raw query string here to allow for multiple parameters with the same key name.
        // The laravel Request object or PHP global array $_GET doesn't allow that.
        $requestQuery = explode('&', array_get($_SERVER, 'QUERY_STRING'));

        // If request is coming from a scripted service then $_SERVER['QUERY_STRING'] will be blank.
        // Therefore need to check the Request object for parameters.
        foreach ($request_params as $pk => $pv) {
            $param = $pk . '=' . $pv;
            if (!in_array($param, $requestQuery)) {
                $requestQuery[] = $param;
            }
        }

        // inbound parameters from request to be passed on
        foreach ($requestQuery as $q) {
            $pairs = explode('=', $q);
            $name = trim(array_get($pairs, 0));
            $value = trim(array_get($pairs, 1));
            $outbound = true;
            $addToCacheKey = true;
            // unless excluded
            if (!empty($parameters)) {
                foreach ($parameters as $param) {
                    if (array_get_bool($param, 'exclude')) {
                        if (0 === strcasecmp($name, strval(array_get($param, 'name')))) {
                            if (static::doesActionApply($param, $action)) {
                                $outbound = !array_get_bool($param, 'outbound', true);
                                $addToCacheKey = !array_get_bool($param, 'cache_key', true);
                            }
                        }
                    }
                }
            }

            static::parseArrayParameter($query, $cache_key, $name, $value, $outbound, $addToCacheKey);
        }

        // DreamFactory additional outbound parameters
        if (!empty($parameters)) {
            foreach ($parameters as $param) {
                if (!array_get_bool($param, 'exclude')) {
                    if (static::doesActionApply($param, $action)) {
                        $name = array_get($param, 'name');
                        $value = array_get($param, 'value');
                        $outbound = array_get_bool($param, 'outbound', true);
                        $addToCacheKey = array_get_bool($param, 'cache_key', true);

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
     * @param array  $request_headers
     *
     * @return void
     */
    protected static function addHeaders($headers, $action, &$options, $request_headers = [])
    {
        if (null === array_get($options, CURLOPT_HTTPHEADER)) {
            $options[CURLOPT_HTTPHEADER] = [];
        }

        // DreamFactory outbound headers, additional and pass through
        if (!empty($headers)) {
            foreach ($headers as $header) {
                if (is_array($header) && static::doesActionApply($header, $action)) {
                    $name = array_get($header, 'name');
                    $value = array_get($header, 'value');
                    if (array_get_bool($header, 'pass_from_client')) {
                        // Check for Basic Auth pulled into server variable already
                        if ((0 === strcasecmp($name, 'Authorization')) &&
                            (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']))
                        ) {
                            $value =
                                'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW']);
                        } else {
                            $phpHeaderName = strtoupper(str_replace(['-', ' '], ['_', '_'], $name));
                            // check for non-standard headers (prefix HTTP_) and standard headers like Content-Type
                            // check in $_SERVER for external requests and $request_headers for internal requests
                            $value =
                                array_get($_SERVER, 'HTTP_' . $phpHeaderName,
                                    array_get($_SERVER, $phpHeaderName,
                                        array_get($_SERVER, $name,
                                            array_get($request_headers, 'HTTP_' . $phpHeaderName,
                                                array_get($request_headers, $phpHeaderName,
                                                    array_get($request_headers, $name))))));
                        }
                    }
                    Session::replaceLookups($value, true);
                    $options[CURLOPT_HTTPHEADER][] = $name . ': ' . $value;
                }
            }
        }
    }

    protected static function cleanOptions(&$options)
    {
        /**
         * 2016-01-21 GHA
         * Add support for proxying remote web service request using configurable CURL options
         */
        if (!empty($options)) {
            $clearKeys = [];
            foreach ($options as $key => $value) {
                if (is_string($value) && (0 === stripos($value, 'CURL')) && defined($value)) {
                    $value = constant($value);
                }
                // all cURL options must be integers
                if (!is_numeric($key)) {
                    if (defined($key)) {
                        $options[constant($key)] = $value;
                        $clearKeys[] = $key;
                    } else {
                        throw new InternalServerErrorException("Invalid configuration: $key is not a defined option.");
                    }
                } else {
                    $options[intval($key)] = $value;
                    $clearKeys[] = $key;
                }
            }
            foreach ($clearKeys as $key) {
                unset($options[$key]);
            }
        }
    }

    public function getAccessList()
    {
        $list = parent::getAccessList();

        $paths = array_keys((array)array_get($this->getApiDoc(), 'paths'));
        foreach ($paths as $path) {
            // drop service from path
            if (!empty($path = ltrim(strstr(ltrim($path, '/'), '/'), '/'))) {
                $list[] = $path;
                $path = explode("/", $path);
                end($path);
                while ($level = prev($path)) {
                    $list[] = $level . '/*';
                }
            }
        }

        natsort($list);

        return array_values(array_unique($list));
    }

    /**
     * @throws \DreamFactory\Core\Exceptions\RestException
     * @return ServiceResponseInterface
     */
    protected function processRequest()
    {
        if (!$this->implementsAccessList &&
            (Verbs::GET === $this->action) &&
            ($this->request->getParameterAsBool(ApiOptions::AS_ACCESS_LIST))
        ) {
            $result = ResourcesWrapper::wrapResources($this->getAccessList());

            return ResponseFactory::create($result);
        }

        $query = '';
        $cacheQuery = '';
        $options = $this->options;

        //	set outbound headers
        $this->addHeaders($this->headers, $this->action, $options, $this->request->getHeaders());

        //  set outbound parameters
        $this->buildParameterString($this->parameters, $this->action, $query, $cacheQuery,
            $this->request->getParameters());

        $data = $this->request->getContent();

        $resource = array_map('rawurlencode', $this->resourceArray);
        if (!empty($resource)) {
            $url = rtrim($this->baseUrl, '/') . '/' . implode('/', $resource);
        } else {
            $url = $this->baseUrl;
        }

        if (!empty($query)) {
            $splicer = (false === strpos($this->baseUrl, '?')) ? '?' : '&';
            $url .= $splicer . $query;
        }

        $cacheKey = '';
        if ($this->cacheEnabled) {
            switch ($this->action) {
                case Verbs::GET:
                    // build cache_key
                    $cacheKey = $this->action . ':' . $this->name;
                    if ($resource) {
                        $cacheKey .= ':' . implode('.', $resource);
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

        Log::debug('Outbound HTTP request: ' . $this->action . ': ' . $url);

        Curl::setDecodeToArray(true);
        $result = Curl::request($this->action, $url, $data, $options);
        $resultHeaders = Curl::getLastResponseHeaders();

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
        $result = $this->fixLinks($result);
        $response = ResponseFactory::create($result, $contentType, $status);
        if ('chunked' === array_get(array_change_key_case($resultHeaders, CASE_LOWER), 'transfer-encoding')) {
            // don't relay this header through to client as it isn't handled well in some cases
            unset($resultHeaders['Transfer-Encoding']); // normal header case
            unset($resultHeaders['transfer-encoding']); // Restlet has all lower for this header
        }
        $response->setHeaders($resultHeaders);

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
     * Replaces any hyper links referencing any DF remote web service's base url
     * with the link to the corresponding DF service itself to ensure DF rws works
     * as the gateway to all remote service calls.
     *
     * @param $result
     *
     * @return mixed
     */
    protected function fixLinks($result)
    {
        if (false === $this->replaceLinks) {
            return $result;
        }

        $isArray = false;
        $string = '';
        if (is_array($result)) {
            $string = json_encode($result, JSON_UNESCAPED_SLASHES);
            $isArray = true;
        } elseif (is_string($result)) {
            $string = $result;
        }

        $myUri = StatusController::getURI($_SERVER);

        $allRws = Service::whereType('rws')->whereIsActive(1)->get()->all();
        if (!empty($allRws)) {
            /** @var Service $rws */
            foreach ($allRws as $rws) {
                $config = $rws->getConfigAttribute();
                $baseUrl = trim(array_get($config, 'base_url'), '/');
                $dfUrl = trim($myUri, '/') . '/api/v2/' . $rws->name;
                $string = str_replace($baseUrl, $dfUrl, $string);
            }
        }

        return ($isArray) ? json_decode($string, true) : $string;
    }

    protected function getEventName()
    {
        if (!empty($this->resourcePath) && (null !== $match = $this->matchEventPath($this->resourcePath))) {
            return parent::getEventName() . '.' . $match['path'];
        }

        return parent::getEventName();
    }

    protected function getEventResource()
    {
        if (!empty($this->resourcePath) && (null !== $match = $this->matchEventPath($this->resourcePath))) {
            return $match['resource'];
        }

        return parent::getEventResource();
    }

    protected function matchEventPath($search)
    {
        $paths = array_keys((array)array_get($this->getApiDoc(), 'paths'));
        $pieces = explode('/', $search);
        foreach ($paths as $path) {
            // drop service from path
            $path = trim(strstr(trim($path, '/'), '/'), '/');
            $pathPieces = explode('/', $path);
            if (count($pieces) === count($pathPieces)) {
                if (empty($diffs = array_diff($pathPieces, $pieces))) {
                    return ['path' => str_replace('/', '.', trim($path, '/')), 'resource' => null];
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
        return ['paths' => [], 'components' => []];
    }
}
