<?php
use Sauladam\ShipmentTracker\ShipmentTracker;
use Sauladam\ShipmentTracker\Track;
use Sauladam\ShipmentTracker\Trackers\AbstractTracker;

/**
 * Created by PhpStorm.
 * User: malte
 * Date: 03.05.2016
 * Time: 23:20
 */
class DachserTest extends TestCase
{

    /**
     * @var \Sauladam\ShipmentTracker\Trackers\DHL
     */
    protected $tracker;


    public function setUp()
    {
        parent::setUp();

        $this->tracker = ShipmentTracker::get('Dachser');
    }


    /** @test */
    public function it_extends_the_abstract_tracker()
    {
        $this->assertInstanceOf(AbstractTracker::class, $this->tracker);
    }

    /**
     * @test
     */
    public function The_NVE_is_contained_in_the_response() {
        $tracker = $this->getTracker('verladeterminal.xml');

        $track = $tracker->track('00342604164600000899');

        $this->assertSame(Track::STATUS_IN_TRANSIT, $track->currentStatus());
        $this->assertFalse($track->delivered());
        $this->assertNull($track->getRecipient());
        $this->assertCount(1, $track->events());

    }

    /** @test */
    public function it_builds_the_tracking_url()
    {
        $url = $this->tracker->trackingUrl('123456789');

        $this->assertSame('http://partner.dachser.com/shp2/?wicket:interface=:5:pnlHead:frmHead:btnSearch::IActivePageBehaviorListener:0:-1&wicket:ignoreIfNotActive=true&random=0.35369399622175934&tfiSearch=123456789', $url);
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
        return $this->getTrackerMock('Dachser', $fileName);
    }

}
