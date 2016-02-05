<?php

namespace Sauladam\ShipmentTracker\HttpClient;

class PhpClient implements HttpClientInterface
{
    /**
     * Request the given url.
     *
     * @param $url
     *
     * @return string
     */
    public function get($url)
    {
        return file_get_contents($url);
    }
}
