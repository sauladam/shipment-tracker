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
     * @param array $options
     *
     * @return string
     */
    public function get($url, $options = [])
    {
        return $this->client->get($url, $options)->getBody()->getContents();
    }
}
