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
    public function Delivered_State_is_parsed_correctly() {
        $tracker = $this->getTracker('delivered.xml');

        $track = $tracker->track('00342604164600000899');

        $this->assertSame(Track::STATUS_DELIVERED, $track->currentStatus());
        $this->assertTrue($track->delivered());

        $this->assertCount(1, $track->events());
        $this->assertSame('Dachser Rheine(Rheine, Germany)', $track->latestEvent()->getLocation());
        $this->assertSame('00342604164600000899', $track->getAdditionalDetails('nve'));
        $this->assertEquals(\Carbon\Carbon::create(2016, 5, 4, 17, 8, 0), $track->latestEvent()->getDate());

    }

    /**
     * @test
     */
    public function VerladeTerminal_State_is_parsed_correctly() {
        $tracker = $this->getTracker('verladeterminal.xml');

        $track = $tracker->track('00342604164600000899');

        $this->assertSame(Track::STATUS_IN_TRANSIT, $track->currentStatus());
        $this->assertFalse($track->delivered());

        $this->assertCount(1, $track->events());
        $this->assertSame('Dachser Bremen(Bremen, Germany)', $track->latestEvent()->getLocation());

        $this->assertEquals(\Carbon\Carbon::create(2016, 5, 3, 0, 0, 0), $track->latestEvent()->getDate());

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
