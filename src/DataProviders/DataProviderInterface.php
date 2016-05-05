<?php

namespace Sauladam\ShipmentTracker\DataProviders;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

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

    /**
     * @param Request $request
     * @return Response
     */
    public function request(Request $request);
}
