<?php

namespace Sauladam\ShipmentTracker\DataProviders;

interface DataProviderInterface
{
    /**
     * Get the contents for the given URL.
     *
     * @param $url
     *
     * @return string
     */
    public function get($url);
}
