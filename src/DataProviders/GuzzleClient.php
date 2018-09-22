<?php

namespace Sauladam\ShipmentTracker\DataProviders;

use GuzzleHttp\Client;

class GuzzleClient implements DataProviderInterface
{
    /**
     * @var Client
     */
    public $client;


    /**
     * GuzzleClient constructor.
     */
    public function __construct()
    {
        $this->client = new Client();
    }


    /**
     * Request the given url.
     *
     * @param $url
     *
     * @return string
     */
    public function get($url)
    {
        return $this->client->get($url)->getBody()->getContents();
    }
}
