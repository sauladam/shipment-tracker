<?php

namespace Sauladam\ShipmentTracker\HttpClient;

interface HttpClientInterface
{
    /**
     * Request the given url.
     *
     * @param $url
     *
     * @return string
     */
    public function get($url);
}
