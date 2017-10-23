<?php

use DreamFactory\Core\Enums\Verbs;

class RwsTest extends \DreamFactory\Core\Testing\TestCase
{
    protected static $staged = false;

    protected $serviceId = 'gmap';

    public function stage()
    {
        parent::stage();

        Artisan::call('migrate');
        Artisan::call('db:seed');

        if (!$this->serviceExists('gmap')) {
            \DreamFactory\Core\Models\Service::create(
                [
                    "name"   => "gmap",
                    "type"   => "rws",
                    "label"  => "Remote web service",
                    "is_active"     => 1,
                    "config" => [
                        "base_url"      => "http://maps.googleapis.com/maps/api/directions/json",
                        "cache_enabled" => false,
                        "parameters"    => [
                            [
                                "name"      => "origin",
                                "value"     => "5415 Winward Parkway Alpharetta, GA",
                                "outbound"  => true,
                                "cache_key" => true,
                                "action"    => 31
                            ],
                            [
                                "name"      => "destination",
                                "value"     => "3600 Mansell Rd. Alpharetta, GA",
                                "outbound"  => true,
                                "cache_key" => true,
                                "action"    => 31
                            ]
                        ]
                    ]
                ]
            );
        }
    }

    public function testGETGmapDirection()
    {
        $rs = $this->makeRequest(Verbs::GET);
        $content = json_encode($rs->getContent());
        $this->assertContains('"routes":[{"bounds":{"northeast":{"lat":34.094355,"lng":-84.2763929},"southwest":{"lat":34.0374132,"lng":-84.2972385}}', $content);
    }
}