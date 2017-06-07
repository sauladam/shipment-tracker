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
     * @param null $default
     *
     * @return array
     */
    public function getAdditionalDetails($key = null, $default = null)
    {
        if (!$key) {
            return $this->additional;
        }

        return array_key_exists($key, $this->additional)
            ? $this->additional[$key]
            : $default;
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
