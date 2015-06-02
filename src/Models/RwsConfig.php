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

namespace DreamFactory\Rave\Rws\Models;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Models\BaseServiceConfigModel;
use Guzzle\Http\Message\Header;

class RwsConfig extends BaseServiceConfigModel
{
    protected $table = 'rws_config';

    protected $fillable = [ 'service_id', 'base_url', 'parameters', 'headers', 'cache_enabled', 'cache_ttl' ];

    protected $appends = [ 'parameters', 'headers' ];

    protected $casts = [ 'cache_enabled' => 'boolean' ];

    protected $parameters = [];

    protected $headers = [];

    public static function boot()
    {
        parent::boot();

        static::created(
            function ( RwsConfig $rwsConfig )
            {
                if ( !empty( $rwsConfig->parameters ) )
                {
                    $params = [];
                    foreach($rwsConfig->parameters as $param)
                    {
                        $params[] = new ParameterConfig($param);
                    }
                    $rwsConfig->parameter()->saveMany($params);
                }

                if ( !empty( $rwsConfig->headers ) )
                {
                    $headers = [];
                    foreach($rwsConfig->headers as $header)
                    {
                        $headers[] = new HeaderConfig($header);
                    }
                    $rwsConfig->header()->saveMany($headers);
                }

                return true;
            }
        );
    }

    public function parameter()
    {
        return $this->hasMany('DreamFactory\Rave\Rws\Models\ParameterConfig', 'service_id');
    }

    public function header()
    {
        return $this->hasMany('DreamFactory\Rave\Rws\Models\HeaderConfig', 'service_id');
    }

    /**
     * @return mixed
     */
    public function getParametersAttribute()
    {
        $this->parameters = $this->parameter()->get()->toArray();

        return $this->parameters;
    }

    /**
     * @param array $val
     */
    public function setParametersAttribute( Array $val )
    {
        $this->parameters = $val;

        if($this->exists)
        {
            $params = [ ];
            foreach ( $this->parameters as $param )
            {
                $p = ParameterConfig::find( ArrayUtils::get( $param, 'id' ) );
                if ( !empty( $p ) )
                {
                    $p->setRawAttributes( $param );
                }
                else
                {
                    $p = new ParameterConfig( $param );
                }
                $params[] = $p;
            }
            $this->parameter()->saveMany( $params );
        }

    }

    /**
     * @return mixed
     */
    public function getHeadersAttribute()
    {
        $this->headers = $this->header()->get()->toArray();

        return $this->headers;
    }

    /**
     * @param array $val
     */
    public function setHeadersAttribute( Array $val )
    {
        $this->headers = $val;

        if($this->exists)
        {
            $headers = [ ];
            foreach ( $this->headers as $header )
            {
                $h = HeaderConfig::find( ArrayUtils::get( $header, 'id' ) );
                if ( !empty( $h ) )
                {
                    $h->setRawAttributes( $header );
                }
                else
                {
                    $h = new HeaderConfig();
                }
                $headers[] = $h;
            }
            $this->header()->saveMany( $headers );
        }
    }
}