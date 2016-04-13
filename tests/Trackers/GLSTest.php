<?php

use Sauladam\ShipmentTracker\ShipmentTracker;
use Sauladam\ShipmentTracker\Trackers\AbstractTracker;

class GLSTest extends TestCase
{
    /**
     * @var \Sauladam\ShipmentTracker\Trackers\GLS
     */
    protected $tracker;


    public function setUp()
    {
        parent::setUp();

        $this->tracker = ShipmentTracker::get('GLS');
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

        $this->assertSame('https://gls-group.eu/DE/de/paketverfolgung?match=123456789', $url);
    }


    /** @test */
    public function it_can_override_the_language_for_the_url()
    {
        $url = $this->tracker->trackingUrl('123456789', 'en');

        $this->assertSame('https://gls-group.eu/DE/en/parcel-tracking?match=123456789', $url);
    }


    /** @test */
    public function it_resolves_a_delivered_shipment()
    {
        $tracker = $this->getTracker([
            'https://gls-group.eu/app/service/open/rest/DE/de/rstt001?match=50346007538' => 'delivered.txt',
            'http://api.customlocation.nokia.com/v1/search/attribute?jsonpCallback=C&appId=s0Ej52VXrLa6AUJEenti&layerId=48&query=%5Blike%5D%2Fname3%2F2760236908&rangeQuery%3D=&limit=1&_1372940610913=' => 'parcel_shop_details.txt'
        ]);

        $track = $tracker->track('50346007538');

        $this->assertSame(\Sauladam\ShipmentTracker\Track::STATUS_DELIVERED, $track->currentStatus());
        $this->assertTrue($track->delivered());
        $this->assertCount(13, $track->events());
    }


    /** @test */
    public function it_resolves_the_recipient_for_a_delivered_shipment()
    {
        $tracker = $this->getTracker([
            'https://gls-group.eu/app/service/open/rest/DE/de/rstt001?match=50346007538' => 'delivered.txt',
            'http://api.customlocation.nokia.com/v1/search/attribute?jsonpCallback=C&appId=s0Ej52VXrLa6AUJEenti&layerId=48&query=%5Blike%5D%2Fname3%2F2760236908&rangeQuery%3D=&limit=1&_1372940610913=' => 'parcel_shop_details.txt'
        ]);

        $track = $tracker->track('50346007538');

        $this->assertSame('TANGER(2760236908)', $track->getRecipient());
    }


    /** @test */
    public function it_sets_the_parcel_shop_details_if_it_the_parcel_was_or_is_delivered_to_a_parcel_shop()
    {
        $tracker = $this->getTracker([
            'https://gls-group.eu/app/service/open/rest/DE/de/rstt001?match=50346007538' => 'delivered.txt',
            'http://api.customlocation.nokia.com/v1/search/attribute?jsonpCallback=C&appId=s0Ej52VXrLa6AUJEenti&layerId=48&query=%5Blike%5D%2Fname3%2F2760236908&rangeQuery%3D=&limit=1&_1372940610913=' => 'parcel_shop_details.txt'
        ]);

        $track = $tracker->track('50346007538');

        $this->assertNotEmpty($track->getAdditionalDetails('parcelShop'));
    }


    /** @test */
    public function it_stores_the_gls_event_number_for_each_event()
    {
        $tracker = $this->getTracker([
            'https://gls-group.eu/app/service/open/rest/DE/de/rstt001?match=50346007538' => 'delivered.txt',
            'http://api.customlocation.nokia.com/v1/search/attribute?jsonpCallback=C&appId=s0Ej52VXrLa6AUJEenti&layerId=48&query=%5Blike%5D%2Fname3%2F2760236908&rangeQuery%3D=&limit=1&_1372940610913=' => 'parcel_shop_details.txt'
        ]);

        $track = $tracker->track('50346007538');

        foreach ($track->events() as $event) {
            $this->assertNotEmpty($event->getAdditionalDetails('eventNumber'));
        }
    }


    /**
     * Build the tracker with a custom test client.
     *
     * @param string|array $fileName
     *
     * @return AbstractTracker
     */
    protected function getTracker($fileName)
    {
        return $this->getTrackerMock('GLS', $fileName);
    }
}
