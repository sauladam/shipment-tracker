<?php

use Sauladam\ShipmentTracker\Event;
use Sauladam\ShipmentTracker\Track;

class TrackTest extends TestCase
{
    /**
     * @var Track
     */
    protected $track;


    public function setUp()
    {
        parent::setUp();

        $this->track = new Track;
    }


    /** @test */
    public function it_can_access_the_events()
    {
        $event = $this->getEvents();

        $this->track->addEvent($event);

        $this->assertTrue($this->track->hasEvents());
        $this->assertCount(1, $this->track->events());
    }


    /** @test */
    public function it_gets_the_latest_event()
    {
        $event1 = $this->getEvents();
        $event2 = $this->getEvents();
        $event3 = $this->getEvents();

        $this->track->addEvent($event1);
        $this->track->addEvent($event2);
        $this->track->addEvent($event3);

        $this->assertCount(3, $this->track->events());
        $this->assertSame($event3, $this->track->latestEvent());
    }


    /** @test */
    public function it_sorts_the_events_by_date_in_descending_order()
    {
        $event1 = Event::fromArray(['date' => '2016-01-31 14:00:00']);
        $event2 = Event::fromArray(['date' => '2016-01-31 15:00:00']);
        $event3 = Event::fromArray(['date' => '2016-01-31 16:00:00']);

        $this->track->addEvent($event3);
        $this->track->addEvent($event1);
        $this->track->addEvent($event2);

        $this->assertSame($event3, $this->track->events()[0]);
        $this->assertSame($event1, $this->track->events()[1]);
        $this->assertSame($event2, $this->track->events()[2]);

        $this->track->sortEvents();

        $this->assertSame($event3, $this->track->events()[0]);
        $this->assertSame($event2, $this->track->events()[1]);
        $this->assertSame($event1, $this->track->events()[2]);
    }


    /**
     * @param int $count
     *
     * @return Event|Event[]
     */
    protected function getEvents($count = 1)
    {
        $eventData = [
            'location' => 'some location',
            'date' => '2016-01-31',
            'description' => 'some description',
            'status' => 'delivered',
        ];

        if ($count == 1) {
            return Event::fromArray($eventData);
        }

        $events = [];

        for ($x = 1; $x <= $count; $x++) {
            $events[] = Event::fromArray($eventData);
        }

        return $events;
    }
}
