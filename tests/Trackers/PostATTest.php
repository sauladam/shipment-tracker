<?php

use Sauladam\ShipmentTracker\ShipmentTracker;
use Sauladam\ShipmentTracker\Track;
use Sauladam\ShipmentTracker\Trackers\AbstractTracker;

class PostATTest extends TestCase
{
    /**
     * @var \Sauladam\ShipmentTracker\Trackers\PostAT
     */
    protected $tracker;


    public function setUp()
    {
        parent::setUp();

        $this->tracker = ShipmentTracker::get('PostAT');
    }


    /** @test */
    public function it_extends_the_abstract_tracker()
    {
        $this->assertInstanceOf(AbstractTracker::class, $this->tracker);
    }


    /** @test */
    public function it_builds_the_tracking_url()
    {
        $url = $this->tracker->trackingUrl('RC320145308DE');

        $this->assertSame(
            'https://www.post.at/sendungsverfolgung.php/details?pnum1=RC320145308DE',
            $url
        );
    }


    /** @test */
    public function it_can_override_the_language_for_the_url()
    {
        $url = $this->tracker->trackingUrl('RC320145308DE', 'en');

        $this->assertSame(
            'https://www.post.at/en/track_trace.php/details?pnum1=RC320145308DE',
            $url
        );
    }


    /** @test */
    public function it_accepts_additional_url_params()
    {
        $url = $this->tracker->trackingUrl('RC320145308DE', null, ['foo' => 'bar']);

        $this->assertSame(
            'https://www.post.at/sendungsverfolgung.php/details?pnum1=RC320145308DE&foo=bar',
            $url
        );
    }


    /** @test */
    public function it_resolves_a_delivered_shipment()
    {
        $tracker = $this->getTracker('delivered.txt');

        $track = $tracker->track('RC320145342DE');

        $this->assertSame(Track::STATUS_DELIVERED, $track->currentStatus());
        $this->assertTrue($track->delivered());
        $this->assertCount(6, $track->events());
    }


    /** @test */
    public function it_resolves_an_in_transit_shipment()
    {
        $tracker = $this->getTracker('in_transit.txt');

        $track = $tracker->track('RC320145308DE');

        $this->assertSame(Track::STATUS_IN_TRANSIT, $track->currentStatus());
        $this->assertFalse($track->delivered());
        $this->assertCount(4, $track->events());
    }


    /** @test */
    public function it_resolves_an_pick_up_shipment()
    {
        $tracker = $this->getTracker('pick_up.txt');

        $track = $tracker->track('RC320145223DE');

        $this->assertSame(Track::STATUS_PICKUP, $track->currentStatus());
        $this->assertFalse($track->delivered());
        $this->assertCount(7, $track->events());
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
        return $this->getTrackerMock('PostAT', $fileName);
    }
}
