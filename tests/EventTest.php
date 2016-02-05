<?php

use Carbon\Carbon;
use Sauladam\ShipmentTracker\Event;

class EventTest extends TestCase
{

    /**
     * @var Event
     */
    protected $event;


    public function setUp()
    {
        parent::setUp();

        $this->event = new Event;
    }


    /** @test */
    public function it_can_access_the_location()
    {
        $this->event->setLocation('some location');

        $this->assertSame('some location', $this->event->getLocation());
    }


    /** @test */
    public function it_can_access_the_date()
    {
        $this->event->setDate(Carbon::parse('2016-01-31'));

        $this->assertSame('2016-01-31', $this->event->getDate()->toDateString());
    }


    /** @test */
    public function it_converts_the_date_to_carbon_if_passed_as_string()
    {
        $this->event->setDate('2016-01-31');

        $this->assertInstanceOf(Carbon::class, $this->event->getDate());
        $this->assertSame('2016-01-31', $this->event->getDate()->toDateString());
    }


    /** @test */
    public function it_can_access_the_description()
    {
        $this->event->setDescription('some description');

        $this->assertSame('some description', $this->event->getDescription());
    }


    /** @test */
    public function it_can_access_the_status()
    {
        $this->event->setStatus('delivered');

        $this->assertSame('delivered', $this->event->getStatus());
    }


    /** @test */
    public function it_can_access_additional_information()
    {
        $this->event->addAdditionalInformation('foo', 'additional info');

        $this->assertTrue($this->event->hasAdditionalInformation());
        $this->assertSame('additional info', $this->event->getAdditionalInformation('foo'));
    }


    /** @test */
    public function it_can_build_an_event_from_array()
    {
        $data = [
            'location' => 'some location',
            'date' => Carbon::parse('2016-01-31'),
            'description' => 'some description',
            'status' => 'delivered',
        ];

        $event = Event::fromArray($data);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame('some location', $event->getLocation());
        $this->assertSame('2016-01-31', $event->getDate()->toDateString());
        $this->assertSame('some description', $event->getDescription());
        $this->assertSame('delivered', $event->getStatus());
    }
}
