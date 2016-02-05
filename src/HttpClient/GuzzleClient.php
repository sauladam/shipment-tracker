<?php

namespace Sauladam\ShipmentTracker\HttpClient;

use GuzzleHttp\Client;

class GuzzleClient implements HttpClientInterface
{
    /**
     * @var Client
     */
    protected $client;


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
