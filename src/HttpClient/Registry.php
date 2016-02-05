<?php

namespace Sauladam\ShipmentTracker\HttpClient;

class Registry
{
    /**
     * @var HttpClientInterface[]
     */
    protected $clients = [];


    /**
     * Get all registered clients.
     *
     * @return HttpClientInterface[]
     */
    public function all()
    {
        return $this->clients;
    }


    /**
     * Register a client under the given name.
     *
     * @param string              $name
     * @param HttpClientInterface $client
     */
    public function register($name, HttpClientInterface $client)
    {
        $this->clients[$name] = $client;
    }


    /**
     * Get the client with the given name.
     *
     * @param $name
     *
     * @return HttpClientInterface
     */
    public function get($name)
    {
        return $this->clients[$name];
    }
}
