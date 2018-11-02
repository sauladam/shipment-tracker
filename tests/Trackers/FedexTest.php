<?php

use Sauladam\ShipmentTracker\ShipmentTracker;

class FedexTest extends TestCase
{
    /**
     * @var \Sauladam\ShipmentTracker\Trackers\Fedex
     */
    protected $tracker;

    public function setUp()
    {
        parent::setUp();

        ShipmentTracker::set('fedex', FedexMock::class);

        $this->tracker = ShipmentTracker::get('fedex');
    }

    public function test_it_resolves_a_delivered_shipment()
    {

        $track = $this->tracker->track(746965179400);

        $this->assertTrue($track->delivered());
        $this->assertCount(9, $track->events());
    }
}
