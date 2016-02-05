<?php

namespace Sauladam\ShipmentTracker;

use Sauladam\ShipmentTracker\HttpClient\GuzzleClient;
use Sauladam\ShipmentTracker\HttpClient\HttpClientInterface;
use Sauladam\ShipmentTracker\HttpClient\PhpClient;
use Sauladam\ShipmentTracker\HttpClient\Registry;
use Sauladam\ShipmentTracker\Trackers\AbstractTracker;

class ShipmentTracker
{
    /**
     * @var string
     */
    protected static $carriersNamespace = "Sauladam\\ShipmentTracker\\Trackers";


    /**
     * Get the tracker for the given carrier name.
     *
     * @param string              $carrier
     * @param HttpClientInterface $httpClient
     *
     * @return AbstractTracker
     * @throws \Exception
     */
    public static function get($carrier, HttpClientInterface $httpClient = null)
    {
        if (!static::isValidCarrier($carrier)) {
            throw new \Exception("Unknwon carrier [{$carrier}]");
        }

        $httpClientRegistry = self::getHttpClientRegistry($httpClient);

        $className = self::$carriersNamespace . '\\' . $carrier;

        $tracker = new $className($httpClientRegistry);

        return $httpClient ? $tracker->useHttpClient('custom') : $tracker;
    }


    /**
     * Get the registry for http clients.
     *
     * @param HttpClientInterface $customClient
     *
     * @return Registry
     */
    protected static function getHttpClientRegistry(HttpClientInterface $customClient = null)
    {
        $registry = new Registry;

        $registry->register('guzzle', new GuzzleClient);
        $registry->register('php', new PhpClient);

        if ($customClient) {
            $registry->register('custom', $customClient);
        }

        return $registry;
    }


    /**
     * Check if a tracker exists for the given carrier.
     *
     * @param string $carrier
     *
     * @return bool
     */
    protected static function isValidCarrier($carrier)
    {
        return class_exists(self::$carriersNamespace . '\\' . $carrier);
    }
}
