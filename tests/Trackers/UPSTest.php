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
            'https://www.ups.com/track?loc=de_DE&tracknum=1ZW5244V6870200569',
            $url
        );
    }


    /** @test */
    public function it_can_override_the_language_for_the_url()
    {
        $url = $this->tracker->trackingUrl('1ZW5244V6870200569', 'en');

        $this->assertSame(
            'https://www.ups.com/track?loc=en_US&tracknum=1ZW5244V6870200569',
            $url
        );
    }


    /** @test */
    public function it_accepts_additional_url_params()
    {
        $url = $this->tracker->trackingUrl('1ZW5244V6870200569', null, ['foo' => 'bar']);

        $this->assertSame(
            'https://www.ups.com/track?loc=de_DE&tracknum=1ZW5244V6870200569&foo=bar',
            $url
        );
    }


    /** @test */
    public function it_resolves_a_delivered_shipment()
    {
        $this->markTestSkipped("Tests coming soon.");

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
        $this->markTestSkipped("Tests coming soon.");

        $tracker = $this->getTracker('exception.txt');

        $track = $tracker->track('1ZW5244V6870129110');

        $this->assertSame(Track::STATUS_EXCEPTION, $track->currentStatus());
        $this->assertContains('The street number is incorrect.', $track->latestEvent()->getDescription());
    }


    /** @test */
    public function it_resolves_an_an_in_transit_status_if_the_shipment_is_on_its_way()
    {
        $this->markTestSkipped("Tests coming soon.");

        $tracker = $this->getTracker('in_transit.txt');

        $track = $tracker->track('1ZW5244V6870200470');

        $this->assertSame(Track::STATUS_IN_TRANSIT, $track->currentStatus());
        $this->assertFalse($track->delivered());
    }


    /** @test */
    public function it_resolves_a_shipment_that_has_to_be_picked_up()
    {
        $this->markTestSkipped("Tests coming soon.");

        $tracker = $this->getTracker('pickup.txt');

        $track = $tracker->track('1ZW5244V6870294478');

        $this->assertSame(Track::STATUS_PICKUP, $track->currentStatus());
        $this->assertFalse($track->delivered());
        $this->assertNull($track->getRecipient());
        $this->assertCount(10, $track->events());
    }


    /** @test */
    public function it_parses_the_access_point_details_and_the_pickup_due_date()
    {
        $this->markTestSkipped("Tests coming soon.");

        $tracker = $this->getTracker('pickup.txt');

        $track = $tracker->track('1ZW5244V6870294478');

        $this->assertSame('REITERSHOP|13 WEIMARER STRASSE|WIEN, 1180 AT', $track->getAdditionalDetails('accessPoint'));
        $this->assertInstanceOf(\Carbon\Carbon::class, $track->getAdditionalDetails('pickupDueDate'));
        $this->assertSame("2017-04-24", $track->getAdditionalDetails('pickupDueDate')->format('Y-m-d'));
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
