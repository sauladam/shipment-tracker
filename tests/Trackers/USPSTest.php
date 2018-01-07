<?php

use Sauladam\ShipmentTracker\ShipmentTracker;
use Sauladam\ShipmentTracker\Track;
use Sauladam\ShipmentTracker\Trackers\AbstractTracker;

class USPSTest extends TestCase
{
    /**
     * @var \Sauladam\ShipmentTracker\Trackers\USPS
     */
    protected $tracker;


    public function setUp()
    {
        parent::setUp();

        $this->tracker = ShipmentTracker::get('USPS');
    }


    /** @test */
    public function it_extends_the_abstract_tracker()
    {
        $this->assertInstanceOf(AbstractTracker::class, $this->tracker);
    }


    /** @test */
    public function it_builds_the_tracking_url()
    {
        $url = $this->tracker->trackingUrl('RT654906222DE');

        $this->assertSame('https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=RT654906222DE', $url);
    }


    /** @test */
    public function it_accepts_additional_url_params()
    {
        $url = $this->tracker->trackingUrl('RT654906222DE', null, ['foo' => 'bar']);

        $this->assertSame('https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=RT654906222DE&foo=bar', $url);
    }


    /** @test */
    public function it_resolves_a_delivered_shipment()
    {
        $tracker = $this->getTracker('delivered.txt');

        $track = $tracker->track('RT654906222DE');

        $this->assertSame(Track::STATUS_DELIVERED, $track->currentStatus());
        $this->assertTrue($track->delivered());
        $this->assertNull($track->getRecipient());
        $this->assertCount(3, $track->events());
    }


    /** @test */
    public function it_resolves_an_an_in_transit_status_if_the_shipment_is_on_its_way()
    {
        $this->markTestSkipped("No real world data available at the moment.");
        return;

        $tracker = $this->getTracker('in_transit.txt');

        $track = $tracker->track('RT654907846DE');

        $this->assertSame(Track::STATUS_IN_TRANSIT, $track->currentStatus());
        $this->assertFalse($track->delivered());
    }


    /**
     * Build the tracker with a custom test client.
     *
     * @param $fileName
     *
     * @return AbstractTracker
     */
    protected function getTracker($fileName)
    {
        return $this->getTrackerMock('USPS', $fileName);
    }
}
