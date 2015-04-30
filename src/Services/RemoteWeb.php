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

namespace DreamFactory\Rave\Rws\Services;

use Log;
use Config;
use DreamFactory\Library\Utility\Curl;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Enums\VerbsMask;
use DreamFactory\Rave\Services\BaseRestService;
use DreamFactory\Rave\Exceptions\RestException;

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
     * @var array
     */
    protected $headers;
    /**
     * @var array
     */
    protected $parameters;
    /**
     * @var array
     */
    protected $excludedParameters;
    /**
     * @var bool
     */
    protected $cacheEnabled;
    /**
     * @var int
     */
    protected $cacheTTL;
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
    protected $curlOptions = array();

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
    public function __construct( $settings )
    {
        parent::__construct( $settings );
        $this->autoDispatch = false;
        $this->query = '';
        $this->_cacheQuery = '';

        $config = ArrayUtils::get($settings, 'config', array());
        $this->baseUrl = ArrayUtils::get($config, 'base_url');

        // Validate url setup
        if ( empty( $this->baseUrl ) )
        {
            throw new \InvalidArgumentException( 'Remote Web Service base url can not be empty.' );
        }
        $this->parameters = ArrayUtils::clean(ArrayUtils::get($config, 'parameters', array()));
        $this->headers = ArrayUtils::clean(ArrayUtils::get($config, 'headers', array()));
        $this->setExcludedParameters($config);

        $this->cacheEnabled = intval(ArrayUtils::get($config, 'cache_enabled', 0));
        $this->cacheTTL = intval( ArrayUtils::get($config, 'cache_ttl', Config::get('rave.default_cache_ttl') ) );
    }

    /**
     * Sets the array of excluded parameters based on configuration.
     *
     * @param array $config configuration array
     */
    protected function setExcludedParameters($config)
    {
        $params = ArrayUtils::clean(ArrayUtils::get($config, 'parameters', array()));
        $this->excludedParameters = [];

        foreach($params as $param)
        {
            if(true===boolval(ArrayUtils::get($param, 'exclude', 0)))
            {
                $this->excludedParameters[] = $param;
            }
        }
    }

    /**
     * @param      $query
     * @param      $key
     * @param      $name
     * @param      $value
     * @param bool $add_to_query
     * @param bool $add_to_key
     */
    protected static function parseArrayParameter( &$query, &$key, $name, $value, $add_to_query = true, $add_to_key = true )
    {
        if ( is_array( $value ) )
        {
            foreach ( $value as $sub => $subValue )
            {
                static::parseArrayParameter( $query, $cache_key, $name . '[' . $sub . ']', $subValue, $add_to_query, $add_to_key );
            }
        }
        else
        {
            //Session::replaceLookups( $value, true );
            $_part = urlencode( $name );
            if ( !empty( $value ) )
            {
                $_part .= '=' . urlencode( $value );
            }
            if ( $add_to_query )
            {
                if ( !empty( $query ) )
                {
                    $query .= '&';
                }
                $query .= $_part;
            }
            if ( $add_to_key )
            {
                if ( !empty( $key ) )
                {
                    $key .= '&';
                }
                $key .= $_part;
            }
        }
    }

    /**
     * @param $config
     * @param $action
     *
     * @return bool
     * @throws \DreamFactory\Rave\Exceptions\BadRequestException
     */
    protected static function doesActionApply( $config, $action )
    {
        $excludeVerbMasks = intval(ArrayUtils::get( $config, 'action' ));
        $myActionMask = VerbsMask::toNumeric($action);

        return ($excludeVerbMasks & $myActionMask);
    }

    /**
     * @param array  $parameters
     * @param array  $exclusions
     * @param string $action
     * @param string $query
     * @param string $cache_key
     *
     * @return void
     */
    protected static function buildParameterString( $parameters, $exclusions, $action, &$query, &$cache_key, $requestQuery )
    {
        // inbound parameters from request to be passed on

        foreach ( $requestQuery as $_name => $_value )
        {
            $_outbound = true;
            $_addToCacheKey = true;
            // unless excluded
            foreach ( $exclusions as $_exclusion )
            {
                if ( 0 === strcasecmp( $_name, strval( ArrayUtils::get( $_exclusion, 'name' ) ) ) )
                {
                    if ( static::doesActionApply( $_exclusion, $action ) )
                    {
                        $_outbound = !ArrayUtils::getBool( $_exclusion, 'outbound', true );
                        $_addToCacheKey = !ArrayUtils::getBool( $_exclusion, 'cache_key', true );
                    }
                }
            }

            static::parseArrayParameter( $query, $cache_key, $_name, $_value, $_outbound, $_addToCacheKey );
        }

        // DSP additional outbound parameters
        if ( !empty( $parameters ) )
        {
            foreach ( $parameters as $_param )
            {
                if ( static::doesActionApply( $_param, $action ) )
                {
                    $_name = ArrayUtils::get( $_param, 'name' );
                    $_value = ArrayUtils::get( $_param, 'value' );
                    $_outbound = ArrayUtils::getBool( $_param, 'outbound', true );
                    $_addToCacheKey = ArrayUtils::getBool( $_param, 'cache_key', true );

                    static::parseArrayParameter( $query, $cache_key, $_name, $_value, $_outbound, $_addToCacheKey );
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
    protected static function addHeaders( $headers, $action, &$options )
    {
        if ( null === ArrayUtils::get( $options, CURLOPT_HTTPHEADER ) )
        {
            $options[CURLOPT_HTTPHEADER] = array();
        }

        // DSP outbound headers, additional and pass through
        if ( !empty( $headers ) )
        {
            foreach ( $headers as $_header )
            {
                if ( static::doesActionApply( $_header, $action ) )
                {
                    $_name = ArrayUtils::get( $_header, 'name' );
                    $_value = ArrayUtils::get( $_header, 'value' );
                    if ( ArrayUtils::getBool( $_header, 'pass_from_client' ) )
                    {
                        // Check for Basic Auth pulled into server variable already
                        if ( ( 0 === strcasecmp( $_name, 'Authorization' ) ) &&
                             ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) )
                        {
                            $_value = 'Basic ' . base64_encode( $_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW'] );
                        }
                        else
                        {
                            $_phpHeaderName = 'HTTP_' . strtoupper( str_replace( array( '-', ' ' ), array( '_', '_' ), $_name ) );
                            $_value = ( isset( $_SERVER[$_phpHeaderName] ) ) ? $_SERVER[$_phpHeaderName] : $_value;
                        }
                    }
                    //Session::replaceLookups( $_value, true );
                    $options[CURLOPT_HTTPHEADER][] = $_name . ': ' . $_value;
                }
            }
        }
    }

    /**
     * A chance to pre-process the data.
     *
     * @return mixed|void
     */
    protected function preProcess()
    {
        parent::preProcess();

        $this->checkPermission( $this->getRequestedAction(), $this->name );

        //  set outbound parameters
        $this->buildParameterString( $this->parameters, $this->excludedParameters, $this->action, $this->query, $this->cacheQuery, $this->request->getParameters() );

        //	set outbound headers
        $this->addHeaders( $this->headers, $this->action, $this->curlOptions );
    }

    /**
     * @throws \DreamFactory\Rave\Exceptions\RestException
     * @return bool
     */
    protected function processRequest()
    {
        $data = $this->request->getContent();

        $resource = ( !empty( $this->resourcePath ) ? '/' . ltrim( $this->resourcePath, '/' ) : null );
        $this->url = rtrim( $this->baseUrl, '/' ) . $resource;

        if ( !empty( $this->query ) )
        {
            $splicer = ( false === strpos( $this->baseUrl, '?' ) ) ? '?' : '&';
            $this->url .= $splicer . $this->query;
        }

        // build cache_key
        $cacheKey = $this->action . ':' . $this->name . $resource;
        if ( !empty( $this->cacheQuery ) )
        {
            $splicer = ( false === strpos( $cacheKey, '?' ) ) ? '?' : '&';
            $cacheKey .= $splicer . $this->cacheQuery;
        }

//        if ( $this->cacheEnabled )
//        {
//            switch ( $this->action )
//            {
//                case static::GET:
//                    //Todo: Implement cache
//                    if ( null !== $result = Platform::storeGet( $cacheKey ) )
//                    {
//                        return $result;
//                    }
//                    break;
//            }
//        }

        Log::debug( 'Outbound HTTP request: ' . $this->action . ': ' . $this->url );

        Curl::setDecodeToArray(true);
        $result = Curl::request(
            $this->action,
            $this->url,
            $data,
            $this->curlOptions
        );

        if ( false === $result )
        {
            $error = Curl::getError();
            throw new RestException( ArrayUtils::get( $error, 'code', 500 ), ArrayUtils::get( $error, 'message' ) );
        }

        $status = Curl::getLastHttpCode();
        if ( $status >= 300 )
        {
            if ( !is_string( $result ) )
            {
                $result = json_encode( $result );
            }

            throw new RestException( $status, $result, $status );
        }


//        if ( $this->cacheEnabled )
//        {
//            switch ( $this->action )
//            {
//                case static::GET:
//                    //Todo: Implement cache
//                    //Platform::storeSet( $cacheKey, $result, $this->cacheTTL );
//                    break;
//            }
//        }

        return $result;
    }
}