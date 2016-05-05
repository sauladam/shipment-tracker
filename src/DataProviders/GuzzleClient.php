<?php

namespace Sauladam\ShipmentTracker\DataProviders;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class GuzzleClient implements DataProviderInterface
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
        return $this->request(new Request('GET', $url))->getBody()->getContents();
    }

    public function request(Request $request)
    {
        return $this->client->send($request);
    }
}
