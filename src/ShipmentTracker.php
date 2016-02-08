<?php

namespace Sauladam\ShipmentTracker;

use Sauladam\ShipmentTracker\DataProviders\GuzzleClient;
use Sauladam\ShipmentTracker\DataProviders\DataProviderInterface;
use Sauladam\ShipmentTracker\DataProviders\PhpClient;
use Sauladam\ShipmentTracker\DataProviders\Registry;
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
     * @param string                $carrier
     * @param DataProviderInterface $customDataProvider
     *
     * @return AbstractTracker
     * @throws \Exception
     */
    public static function get($carrier, DataProviderInterface $customDataProvider = null)
    {
        if (!static::isValidCarrier($carrier)) {
            throw new \Exception("Unknown carrier [{$carrier}]");
        }

        $dataProviderRegistry = self::getDataProviderRegistry($customDataProvider);

        $className = self::$carriersNamespace . '\\' . $carrier;

        $tracker = new $className($dataProviderRegistry);

        return $customDataProvider ? $tracker->useDataProvider('custom') : $tracker;
    }


    /**
     * Get the registry for the data providers.
     *
     * @param DataProviderInterface $customProvider
     *
     * @return Registry
     */
    protected static function getDataProviderRegistry(DataProviderInterface $customProvider = null)
    {
        $registry = new Registry;

        $registry->register('guzzle', new GuzzleClient);
        $registry->register('php', new PhpClient);

        if ($customProvider) {
            $registry->register('custom', $customProvider);
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
