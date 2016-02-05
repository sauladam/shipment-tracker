<?php

use Sauladam\ShipmentTracker\ShipmentTracker;
use Sauladam\ShipmentTracker\Track;
use Sauladam\ShipmentTracker\Trackers\AbstractTracker;

class PostCHTest extends TestCase
{
    /**
     * @var \Sauladam\ShipmentTracker\Trackers\PostCH
     */
    protected $tracker;


    public function setUp()
    {
        parent::setUp();

        $this->tracker = ShipmentTracker::get('PostCH');
    }


    /** @test */
    public function it_extends_the_abstract_tracker()
    {
        $this->assertInstanceOf(AbstractTracker::class, $this->tracker);
    }


    /** @test */
    public function it_builds_the_tracking_url()
    {
        $url = $this->tracker->trackingUrl('RB592593703DE');

        $this->assertSame(
            'https://service.post.ch/EasyTrack/submitParcelData.do?formattedParcelCodes=RB592593703DE&lang=de',
            $url
        );
    }


    /** @test */
    public function it_can_override_the_language_for_the_url()
    {
        $url = $this->tracker->trackingUrl('RB592593703DE', 'en');

        $this->assertSame(
            'https://service.post.ch/EasyTrack/submitParcelData.do?formattedParcelCodes=RB592593703DE&lang=en',
            $url
        );
    }


    /** @test */
    public function it_accepts_additional_url_params()
    {
        $url = $this->tracker->trackingUrl('RB592593703DE', null, ['foo' => 'bar']);

        $this->assertSame(
            'https://service.post.ch/EasyTrack/submitParcelData.do?formattedParcelCodes=RB592593703DE&lang=de&foo=bar',
            $url
        );
    }


    /** @test */
    public function it_resolves_a_delivered_shipment()
    {
        $tracker = $this->getTracker('delivered.txt');

        $track = $tracker->track('RB592593703DE');

        $this->assertSame(Track::STATUS_DELIVERED, $track->currentStatus());
        $this->assertTrue($track->delivered());
        $this->assertCount(8, $track->events());
    }

    /** @test */
    public function it_resolves_an_unknown_status_if_there_is_not_data_available_yet()
    {
        $tracker = $this->getTracker('unknown.txt');

        $track = $tracker->track('RB592593703DE');

        $this->assertSame(Track::STATUS_UNKNOWN, $track->currentStatus());
        $this->assertFalse($track->delivered());
        $this->assertCount(0, $track->events());
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
        return $this->getTrackerMock('PostCH', $fileName);
    }
}
