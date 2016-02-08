<?php

use Sauladam\ShipmentTracker\ShipmentTracker;

class TestCase extends PHPUnit_Framework_TestCase
{

    /**
     * @param string $carrier
     * @param null   $fileName
     *
     * @return \Sauladam\ShipmentTracker\Trackers\AbstractTracker
     */
    public function getTrackerMock($carrier, $fileName = null)
    {
        if (!$fileName) {
            return ShipmentTracker::get($carrier);
        }

        $customClient = new FileMapperDataProvider($carrier, $fileName);

        return ShipmentTracker::get($carrier, $customClient);
    }
}
