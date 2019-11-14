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
     * Array of tracker classes
     * @var array
     */
    protected static $customizeTrackerClasses = [];

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
        if (isset(static::$customizeTrackerClasses[$carrier])) {
            $className = static::$customizeTrackerClasses[$carrier];
        } else {
            if (!static::isValidCarrier($carrier)) {
                throw new \Exception("Unknown carrier [{$carrier}]");
            }
            $className = self::$carriersNamespace . '\\' . $carrier;
        }

        $dataProviderRegistry = self::getDataProviderRegistry($customDataProvider);
        $tracker = new $className($dataProviderRegistry);
        return $customDataProvider ? $tracker->useDataProvider('custom') : $tracker;
    }

    /**
     * Registers a customize carrier class
     * @param string $carrier
     * @param string $carrierClass
     * @throws \InvalidArgumentException
     */
    public static function set($carrier, $carrierClass)
    {
        if (!static::isValidCarrierClass($carrierClass)) {
            throw new \InvalidArgumentException(sprintf('The carrier class "%s" is invalid', $carrierClass));
        }
        static::$customizeTrackerClasses[$carrier] = $carrierClass;
    }

    protected static function isValidCarrierClass($carrierClass)
    {
        return class_exists($carrierClass) && is_subclass_of($carrierClass, AbstractTracker::class);
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
