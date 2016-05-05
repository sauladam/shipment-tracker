<?php

namespace Sauladam\ShipmentTracker\DataProviders;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

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

    /**
     * @param Request $request
     * @return Response
     */
    public function request(Request $request)
    {

        if ($request->getMethod() !== 'GET') {
            throw new \InvalidArgumentException('file_get_contents only supports get requests');

        }

        return new Response(200, [], $this->get($request->getUri()));
    }
}
