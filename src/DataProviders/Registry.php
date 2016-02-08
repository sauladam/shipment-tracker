<?php

namespace Sauladam\ShipmentTracker\DataProviders;

class Registry
{
    /**
     * @var DataProviderInterface[]
     */
    protected $providers = [];


    /**
     * Get all registered providers.
     *
     * @return DataProviderInterface[]
     */
    public function all()
    {
        return $this->providers;
    }


    /**
     * Register a provider under the given name.
     *
     * @param string                $name
     * @param DataProviderInterface $provider
     */
    public function register($name, DataProviderInterface $provider)
    {
        $this->providers[$name] = $provider;
    }


    /**
     * Get the provider with the given name.
     *
     * @param $name
     *
     * @return DataProviderInterface
     */
    public function get($name)
    {
        return $this->providers[$name];
    }
}
