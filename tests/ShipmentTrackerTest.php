<?php

use Sauladam\ShipmentTracker\ShipmentTracker;
use Sauladam\ShipmentTracker\Trackers\DHL;
use Sauladam\ShipmentTracker\Trackers\Fedex;
use Sauladam\ShipmentTracker\Trackers\GLS;
use Sauladam\ShipmentTracker\Trackers\PostCH;
use Sauladam\ShipmentTracker\Trackers\UPS;
use Sauladam\ShipmentTracker\Trackers\USPS;

class ShipmentTrackerTest extends TestCase
{
    /** @test */
    public function it_resolves_the_dhl_tracker()
    {
        $tracker = ShipmentTracker::get('DHL');

        $this->assertInstanceOf(DHL::class, $tracker);
    }


    /** @test */
    public function it_resolves_the_gls_tracker()
    {
        $tracker = ShipmentTracker::get('GLS');

        $this->assertInstanceOf(GLS::class, $tracker);
    }


    /** @test */
    public function it_resolves_the_ups_tracker()
    {
        $tracker = ShipmentTracker::get('UPS');

        $this->assertInstanceOf(UPS::class, $tracker);
    }


    /** @test */
    public function it_resolves_the_usps_tracker()
    {
        $tracker = ShipmentTracker::get('USPS');

        $this->assertInstanceOf(USPS::class, $tracker);
    }


    /** @test */
    public function it_resolves_the_post_ch_tracker()
    {
        $tracker = ShipmentTracker::get('PostCH');

        $this->assertInstanceOf(PostCH::class, $tracker);
    }

    /** @test */
    public function it_resolves_the_fedex_tracker()
    {
        $tracker = ShipmentTracker::get('Fedex');

        $this->assertInstanceOf(Fedex::class, $tracker);
    }


    /**
     * @test
     * @expectedException Exception
     */
    public function it_throws_an_exception_if_the_carrier_is_unknown()
    {
        ShipmentTracker::get('some-nonexistent-tracker');
    }

    /**
     * @test
     */
    public function testSetCustomizeCarrier()
    {
        try {
            ShipmentTracker::get('foo-carrier');
            $this->fail();
        } catch (\Exception $exception) {
            $this->assertInstanceOf(Exception::class, $exception);
            ShipmentTracker::set('foo-carrier', ValidTracker::class);
            $tracker = ShipmentTracker::get('foo-carrier');
            $this->assertInstanceOf(ValidTracker::class, $tracker);
        }
        try{
            ShipmentTracker::set('bar-carrier', InvalidTracker::class);
        } catch (\Exception $exception) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        }
        try{
            ShipmentTracker::set('baz-carrier', 'NotExistingCarrier');
        } catch (\Exception $exception) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        }
    }
}
