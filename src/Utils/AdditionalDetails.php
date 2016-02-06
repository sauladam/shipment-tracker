<?php

namespace Sauladam\ShipmentTracker\Utils;

trait AdditionalDetails
{
    /**
     * @var array
     */
    protected $additional = [];


    /**
     * Add additional information.
     *
     * @param $key
     * @param $data
     *
     * @return $this
     */
    public function addAdditionalDetails($key, $data)
    {
        $this->additional[$key] = $data;

        return $this;
    }


    /**
     * Get additional information.
     *
     * @param string|null $key
     *
     * @return array
     * @throws \Exception
     */
    public function getAdditionalDetails($key = null)
    {
        if (!$key) {
            return $this->additional;
        }

        if (!array_key_exists($key, $this->additional)) {
            throw  new \Exception("No additional data set for [{$key}].");
        }

        return $this->additional[$key];
    }


    /**
     * Check if there is additional information.
     *
     * @return bool
     */
    public function hasAdditionalDetails()
    {
        return !empty($this->additional);
    }
}
