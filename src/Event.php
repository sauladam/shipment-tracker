<?php

namespace Sauladam\ShipmentTracker;

use Carbon\Carbon;
use Sauladam\ShipmentTracker\Utils\AdditionalDetails;
use Sauladam\ShipmentTracker\Utils\Utils;

class Event
{
    use AdditionalDetails;

    /**
     * @var string
     */
    protected $location;

    /**
     * @var Carbon
     */
    protected $date;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $status;


    /**
     * Get the location.
     *
     * @return string
     */
    public function getLocation()
    {
        return $this->location;
    }


    /**
     * Set the location.
     *
     * @param $location
     *
     * @return $this
     */
    public function setLocation($location)
    {
        $this->location = $location;

        return $this;
    }


    /**
     * Get the date.
     *
     * @return Carbon
     */
    public function getDate()
    {
        return $this->date;
    }


    /**
     * Set the date.
     *
     * @param string|Carbon $date
     *
     * @return $this
     */
    public function setDate($date)
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        $this->date = $date;

        return $this;
    }


    /**
     * Get the description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }


    /**
     * Set the description.
     *
     * @param $description
     *
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = Utils::ensureUtf8($description);

        return $this;
    }


    /**
     * Get the status during this event.
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }


    /**
     * Set the status.
     *
     * @param $status
     *
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }


    /**
     * Create an event from the given array.
     *
     * @param array $data
     *
     * @return Event
     */
    public static function fromArray(array $data)
    {
        $event = new self;

        $eligibleKeys = ['date', 'location', 'description', 'status'];

        $data = array_intersect_key($data, array_flip($eligibleKeys));

        foreach ($data as $key => $value) {
            $setter = 'set' . ucfirst($key);

            $event->{$setter}($value);
        }

        return $event;
    }
}
