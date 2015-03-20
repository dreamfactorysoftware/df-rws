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

use DreamFactory\Rave\Services\BaseRestService;
use DreamFactory\Library\Utility\Curl;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Exceptions\RestException;
use DreamFactory\Rave\Utility\DbUtilities;

class RemoteWebService extends BaseRestService
{
    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var string
     */
    protected $_baseUrl;
    /**
     * @var array
     */
    protected $_credentials;
    /**
     * @var array
     */
    protected $_headers;
    /**
     * @var array
     */
    protected $_parameters;
    /**
     * @var array
     */
    protected $_excludedParameters;
    /**
     * @var bool
     */
    protected $_cacheEnabled;
    /**
     * @var int
     */
    protected $_cacheTTL;
    /**
     * @var bool
     */
    protected $_cacheMatchBody;
    /**
     * @var string
     */
    protected $_cacheQuery;
    /**
     * @var string
     */
    protected $_query;
    /**
     * @var string
     */
    protected $_url;
    /**
     * @var array
     */
    protected $_curlOptions = array();

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Create a new RemoteWebService
     *
     * @param array $config configuration array
     *
     * @throws \InvalidArgumentException
     */
    public function __construct( $config )
    {
        parent::__construct( $config );

        $this->setAutoDispatch( false );

        // Validate url setup
        if ( empty( $this->_baseUrl ) )
        {
            throw new \InvalidArgumentException( 'Remote Web Service base url can not be empty.' );
        }

        $this->_excludedParameters = ArrayUtils::getDeep( $this->_credentials, 'client_exclusions', 'parameters', array() );

//        $_cacheConfig = ArrayUtils::get( $this->_credentials, 'cache_config' );

        $this->_cacheEnabled = false;
        //Todo: Implement cache
//        $this->_cacheEnabled = ArrayUtils::getBool( $_cacheConfig, 'enabled' );
//        $this->_cacheTTL = intval( ArrayUtils::get( $_cacheConfig, 'ttl', Platform::DEFAULT_CACHE_TTL ) );
//        $this->_cacheMatchBody = ArrayUtils::getBool( $_cacheConfig, 'match_body' );

        $this->_query = '';
        $this->_cacheQuery = '';
    }

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

    protected static function doesActionApply( $config, $action )
    {
        if ( null !== $_excludeActions = ArrayUtils::get( $config, 'action' ) )
        {
            if ( !( is_string( $_excludeActions ) && 0 === strcasecmp( 'all', $_excludeActions ) ) )
            {
                $_excludeActions = DbUtilities::validateAsArray( $_excludeActions, ',', true, 'Exclusion action config is invalid.' );
                if ( false === array_search( $action, $_excludeActions ) )
                {
                    return false;
                }
            }
        }

        return true;
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
    protected static function buildParameterString( $parameters, $exclusions, $action, &$query, &$cache_key )
    {
        // inbound parameters from request to be passed on
        foreach ( $_REQUEST as $_name => $_value )
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
    protected function _preProcess()
    {
        parent::preProcess();

        $this->checkPermission( $this->getRequestedAction(), $this->name );

        //  set outbound parameters
        $this->buildParameterString( $this->_parameters, $this->_excludedParameters, $this->action, $this->_query, $this->_cacheQuery );

        //	set outbound headers
        $this->addHeaders( $this->_headers, $this->action, $this->_curlOptions );
    }

    /**
     * @throws \DreamFactory\Rave\Exceptions\RestException
     * @return bool
     */
    protected function handleResource()
    {
        $_data = $this->getPayloadData();

        $_resource = ( !empty( $this->resourcePath ) ? '/' . ltrim( $this->resourcePath, '/' ) : null );
        $this->_url = rtrim( $this->_baseUrl, '/' ) . $_resource;

        if ( !empty( $this->_query ) )
        {
            $_splicer = ( false === strpos( $this->_baseUrl, '?' ) ) ? '?' : '&';
            $this->_url .= $_splicer . $this->_query;
        }

        // build cache_key
//        $_cacheKey = $this->action . ':' . $this->name . $_resource;
//        if ( !empty( $this->_cacheQuery ) )
//        {
//            $_splicer = ( false === strpos( $_cacheKey, '?' ) ) ? '?' : '&';
//            $_cacheKey .= $_splicer . $this->_cacheQuery;
//        }

        if ( $this->_cacheEnabled )
        {
//            switch ( $this->action )
//            {
//                case static::GET:
//                    if ( null !== $_result = Platform::storeGet( $_cacheKey ) )
//                    {
//                        return $_result;
//                    }
//                    break;
//            }
        }

        //Log::debug( 'Outbound HTTP request: ' . $this->action . ': ' . $this->_url );

        $_result = Curl::request(
            $this->action,
            $this->_url,
            $_data,
            $this->_curlOptions
        );

        if ( false === $_result )
        {
            $_error = Curl::getError();
            throw new RestException( ArrayUtils::get( $_error, 'code', 500 ), ArrayUtils::get( $_error, 'message' ) );
        }

        $_status = Curl::getLastHttpCode();
        if ( $_status >= 300 )
        {
            if ( !is_string( $_result ) )
            {
                $_result = json_encode( $_result );
            }

            throw new RestException( $_status, $_result, $_status );
        }

        if ( $this->_cacheEnabled )
        {
//            switch ( $this->action )
//            {
//                case static::GET:
//                    Platform::storeSet( $_cacheKey, $_result, $this->_cacheTTL );
//                    break;
//            }
        }

        return $_result;
    }

    /**
     * @param string $baseUrl
     *
     * @return RemoteWebService
     */
    public function setBaseUrl( $baseUrl )
    {
        $this->_baseUrl = $baseUrl;

        return $this;
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->_baseUrl;
    }

    /**
     * @param array $credentials
     *
     * @return RemoteWebService
     */
    public function setCredentials( $credentials )
    {
        $this->_credentials = $credentials;

        return $this;
    }

    /**
     * @return array
     */
    public function getCredentials()
    {
        return $this->_credentials;
    }

    /**
     * @param array $headers
     *
     * @return RemoteWebService
     */
    public function setHeaders( $headers )
    {
        $this->_headers = $headers;

        return $this;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->_headers;
    }

    /**
     * @param array $parameters
     *
     * @return RemoteWebService
     */
    public function setParameters( $parameters )
    {
        $this->_parameters = $parameters;

        return $this;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->_parameters;
    }
}