<?php

use Sauladam\ShipmentTracker\ShipmentTracker;
use Sauladam\ShipmentTracker\Track;
use Sauladam\ShipmentTracker\Trackers\AbstractTracker;
use Sauladam\ShipmentTracker\Trackers\DHLExpress;

class DHLExpressTest extends TestCase
{
    /**
     * @var DHLExpress
     */
    protected $tracker;


    public function setUp()
    {
        parent::setUp();

        $this->tracker = ShipmentTracker::get('DHLExpress');
    }


    /** @test */
    public function it_extends_the_abstract_tracker()
    {
        $this->assertInstanceOf(AbstractTracker::class, $this->tracker);
    }


    /** @test */
    public function it_builds_the_correct_tracker_class()
    {
        $this->assertInstanceOf(DHLExpress::class, $this->tracker);
    }


    /** @test */
    public function it_builds_the_tracking_url()
    {
        $englishUrl = $this->tracker->trackingUrl('123456789', 'en');
        $germanUrl = $this->tracker->trackingUrl('123456789', 'de');

        $prefix = 'http://www.dhl.com/en/hidden/component_library/express/local_express/dhl_de_tracking/';

        $this->assertSame($prefix . 'en/tracking_dhlde.html?AWB=123456789&brand=DHL', $englishUrl);
        $this->assertSame($prefix . 'de/sendungsverfolgung_dhlde.html?AWB=123456789&brand=DHL', $germanUrl);
    }


    /** @test */
    public function it_accepts_additional_url_params()
    {
        $url = $this->tracker->trackingUrl('123456789', null, ['foo' => 'bar']);

        $prefix = 'http://www.dhl.com/en/hidden/component_library/express/local_express/dhl_de_tracking/';

        $this->assertSame(
            $prefix . 'de/sendungsverfolgung_dhlde.html?AWB=123456789&brand=DHL&foo=bar',
            $url
        );
    }


    /** @test */
    public function it_resolves_a_delivered_shipment()
    {
        $tracker = $this->getTracker('delivered.txt');

        $track = $tracker->track('5765159960');

        $this->assertSame(Track::STATUS_DELIVERED, $track->currentStatus());
        $this->assertTrue($track->delivered());
        $this->assertCount(16, $track->events());
    }


    /** @test */
    public function it_resolves_the_recipient_for_a_delivered_shipment()
    {
        $tracker = $this->getTracker('delivered.txt');

        $track = $tracker->track('5765159960');

        $this->assertSame('SDA', $track->getRecipient());
    }


    /** @test */
    public function it_resolves_a_shipment_that_is_in_transit()
    {
        $tracker = $this->getTracker('in_transit.txt');

        $track = $tracker->track('5765159960');

        $this->assertSame(Track::STATUS_IN_TRANSIT, $track->currentStatus());
        $this->assertFalse($track->delivered());
        $this->assertNull($track->getRecipient());
        $this->assertCount(15, $track->events());
    }


    /** @test */
    public function it_resolves_a_shipment_as_delivered_even_if_the_statuses_are_not_in_chronological_order()
    {
        $tracker = $this->getTracker('delivered_with_unordered_statuses.txt');

        $track = $tracker->track('5765159960');

        $this->assertSame(Track::STATUS_DELIVERED, $track->currentStatus());
        $this->assertTrue($track->delivered());
        $this->assertSame($track->getRecipient(), 'SDA');
        $this->assertCount(16, $track->events());
    }


    /** @test */
    public function it_adds_the_pieces_ids_to_the_events()
    {
        $tracker = $this->getTracker('delivered.txt');

        $track = $tracker->track('5765159960');

        foreach ($track->events() as $event) {
            if (!$event->hasAdditionalDetails()) {
                continue;
            }

            $pieces = $event->getAdditionalDetails('pieces');

            $this->assertCount(1, $pieces);
            $this->assertSame('JD014600004444917061', $pieces[0]);
        }
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
        return $this->getTrackerMock('DHLExpress', $fileName);
    }
}
