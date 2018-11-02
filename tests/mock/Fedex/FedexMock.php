<?php

use Sauladam\ShipmentTracker\Trackers\Fedex;

class FedexMock extends Fedex
{
    protected function fetch($url)
    {
        return file_get_contents('fedex_shipment.json', FILE_USE_INCLUDE_PATH);
    }
}
