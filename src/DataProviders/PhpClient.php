<?php

namespace Sauladam\ShipmentTracker\DataProviders;

class PhpClient implements DataProviderInterface
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
