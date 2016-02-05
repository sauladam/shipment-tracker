<?php

use Sauladam\ShipmentTracker\ShipmentTracker;
use Sauladam\ShipmentTracker\Track;
use Sauladam\ShipmentTracker\Trackers\AbstractTracker;

class DHLTest extends TestCase
{
    /**
     * @var \Sauladam\ShipmentTracker\Trackers\DHL
     */
    protected $tracker;


    public function setUp()
    {
        parent::setUp();

        $this->tracker = ShipmentTracker::get('DHL');
    }


    /** @test */
    public function it_extends_the_abstract_tracker()
    {
        $this->assertInstanceOf(AbstractTracker::class, $this->tracker);
    }


    /** @test */
    public function it_builds_the_tracking_url()
    {
        $url = $this->tracker->trackingUrl('123456789');

        $this->assertSame('http://nolp.dhl.de/nextt-online-public/set_identcodes.do?lang=de&idc=123456789', $url);
    }


    /** @test */
    public function it_can_override_the_language_for_the_url()
    {
        $url = $this->tracker->trackingUrl('123456789', 'en');

        $this->assertSame('http://nolp.dhl.de/nextt-online-public/set_identcodes.do?lang=en&idc=123456789', $url);
    }


    /** @test */
    public function it_accepts_additional_url_params()
    {
        $url = $this->tracker->trackingUrl('123456789', null, ['foo' => 'bar']);

        $this->assertSame(
            'http://nolp.dhl.de/nextt-online-public/set_identcodes.do?lang=de&idc=123456789&foo=bar',
            $url
        );
    }


    /** @test */
    public function it_resolves_a_delivered_shipment()
    {
        $tracker = $this->getTracker('delivered.txt');

        $track = $tracker->track('00340433924192908894');

        $this->assertSame(Track::STATUS_DELIVERED, $track->currentStatus());
        $this->assertTrue($track->delivered());
        $this->assertCount(5, $track->events());
    }


    /** @test */
    public function it_resolves_the_recipient_for_a_delivered_shipment()
    {
        $tracker = $this->getTracker('delivered.txt');

        $track = $tracker->track('00340433924192908894');

        $this->assertSame('Empfänger (orig.)', $track->getRecipient());
    }


    /** @test */
    public function it_resolves_a_shipment_that_has_to_be_picked_up()
    {
        $tracker = $this->getTracker('pickup.txt');

        $track = $tracker->track('00340433924192908764');

        $this->assertSame(Track::STATUS_PICKUP, $track->currentStatus());
        $this->assertFalse($track->delivered());
        $this->assertNull($track->getRecipient());
        $this->assertCount(6, $track->events());
    }


    /** @test */
    public function it_resolves_a_shipment_that_is_in_transit()
    {
        $tracker = $this->getTracker('in_transit.txt');

        $track = $tracker->track('00340433924192908283');

        $this->assertSame(Track::STATUS_IN_TRANSIT, $track->currentStatus());
        $this->assertFalse($track->delivered());
        $this->assertNull($track->getRecipient());
        $this->assertCount(2, $track->events());
    }


    /** @test */
    public function it_resolves_a_shipment_as_delivered_even_if_the_statuses_are_not_in_chronological_order()
    {
        $tracker = $this->getTracker('delivered_with_unordered_statuses.txt');

        $track = $tracker->track('00340433924192991025');

        $this->assertNotSame(Track::STATUS_DELIVERED, $track->currentStatus());
        $this->assertTrue($track->delivered());
        $this->assertSame($track->getRecipient(), 'Empfänger (orig.)');
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
        return $this->getTrackerMock('DHL', $fileName);
    }
}
