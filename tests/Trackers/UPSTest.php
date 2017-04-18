<?php

use Sauladam\ShipmentTracker\ShipmentTracker;
use Sauladam\ShipmentTracker\Track;
use Sauladam\ShipmentTracker\Trackers\AbstractTracker;

class UPSTest extends TestCase
{
    /**
     * @var \Sauladam\ShipmentTracker\Trackers\UPS
     */
    protected $tracker;


    public function setUp()
    {
        parent::setUp();

        $this->tracker = ShipmentTracker::get('UPS');
    }


    /** @test */
    public function it_extends_the_abstract_tracker()
    {
        $this->assertInstanceOf(AbstractTracker::class, $this->tracker);
    }


    /** @test */
    public function it_builds_the_tracking_url()
    {
        $url = $this->tracker->trackingUrl('1ZW5244V6870200569');

        $this->assertSame(
            'http://wwwapps.ups.com/WebTracking/track?loc=de_DE&track=yes&trackNums=1ZW5244V6870200569',
            $url
        );
    }


    /** @test */
    public function it_can_override_the_language_for_the_url()
    {
        $url = $this->tracker->trackingUrl('1ZW5244V6870200569', 'en');

        $this->assertSame(
            'http://wwwapps.ups.com/WebTracking/track?loc=en_US&track=yes&trackNums=1ZW5244V6870200569',
            $url
        );
    }


    /** @test */
    public function it_accepts_additional_url_params()
    {
        $url = $this->tracker->trackingUrl('1ZW5244V6870200569', null, ['foo' => 'bar']);

        $this->assertSame(
            'http://wwwapps.ups.com/WebTracking/track?loc=de_DE&track=yes&trackNums=1ZW5244V6870200569&foo=bar',
            $url
        );
    }


    /** @test */
    public function it_resolves_a_delivered_shipment()
    {
        $tracker = $this->getTracker('delivered.txt');

        $track = $tracker->track('1ZW5244V6870200569');

        $this->assertSame(Track::STATUS_DELIVERED, $track->currentStatus());
        $this->assertSame('PROSENITCH', $track->getRecipient());
        $this->assertTrue($track->delivered());
        $this->assertCount(13, $track->events());
    }


    /** @test */
    public function it_resolves_an_exception_if_there_is_a_problem()
    {
        $tracker = $this->getTracker('exception.txt');

        $track = $tracker->track('1ZW5244V6870129110');

        $this->assertSame(Track::STATUS_EXCEPTION, $track->currentStatus());
        $this->assertContains('The street number is incorrect.', $track->latestEvent()->getDescription());
    }


    /** @test */
    public function it_resolves_an_an_in_transit_status_if_the_shipment_is_on_its_way()
    {
        $tracker = $this->getTracker('in_transit.txt');

        $track = $tracker->track('1ZW5244V6870200470');

        $this->assertSame(Track::STATUS_IN_TRANSIT, $track->currentStatus());
        $this->assertFalse($track->delivered());
    }


    /** @test */
    public function it_resolves_a_shipment_that_has_to_be_picked_up()
    {
        $tracker = $this->getTracker('pickup.txt');

        $track = $tracker->track('1ZW5244V6870294478');

        $this->assertSame(Track::STATUS_PICKUP, $track->currentStatus());
        $this->assertFalse($track->delivered());
        $this->assertNull($track->getRecipient());
        $this->assertCount(10, $track->events());
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
        return $this->getTrackerMock('UPS', $fileName);
    }
}
