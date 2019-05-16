<?php

namespace Sauladam\ShipmentTracker\Trackers;

use Exception;
use Carbon\Carbon;
use Sauladam\ShipmentTracker\Event;
use Sauladam\ShipmentTracker\Track;
use Sauladam\ShipmentTracker\Utils\XmlHelpers;

class PostAT extends AbstractTracker
{
    use XmlHelpers;

    protected $serviceEndpoints = [
        'de' => 'https://www.post.at/sendungsverfolgung.php/details',
        'en' => 'https://www.post.at/en/track_trace.php/details',
    ];

    protected $language = 'de';


    /**
     * Build the url to the user friendly tracking site. In most
     * cases this is also the endpoint, but sometimes the tracking
     * data must be retrieved from another endpoint.
     *
     * @param string $trackingNumber
     * @param string|null $language
     * @param array $params
     *
     * @return string
     */
    public function trackingUrl($trackingNumber, $language = null, $params = [])
    {
        $params = array_merge($this->trackingUrlParams, [
            'pnum1' => $trackingNumber,
        ], $params);

        $language = in_array($language, array_keys($this->serviceEndpoints))
            ? $language
            : $this->language;

        return $this->serviceEndpoints[$language] . '?' . http_build_query($params);
    }

    /**
     * Build the response array.
     *
     * @param string $response
     *
     * @return Track
     */
    protected function buildResponse($response)
    {
        $rows = $this->toXpath($response)->query("//div[@class='sendungsstatus-history']//ul//li");

        if (!$rows) {
            throw new Exception("Could not parse tracking information for [{$this->parcelNumber}].");
        }

        return array_reduce($this->nodeListToArray($rows), function ($track, $row) {
            return $track->addEvent($this->eventFromRow($row));
        }, new Track)->sortEvents();
    }


    /**
     * Parse the event data from the given node.
     *
     * @param \DOMNode $row
     *
     * @return Event
     */
    protected function eventFromRow($row)
    {
        preg_match(
            '/(date|datum): ([\d.:\s]+)(.*?)(?:; (.*)|$)/i',
            utf8_decode($this->getNodeValue($row)),
            $matches
        );

        return count($matches) < 4
            ? new Event
            : Event::fromArray([
                'date' => Carbon::parse($matches[2]),
                'description' => $matches[3],
                'location' => isset($matches[4]) ? $matches[4] : '',
                'status' => $this->resolveStatus($matches[3]),
            ]);
    }


    /**
     * Match a shipping status from the given description.
     *
     * @param $statusDescription
     *
     * @return string
     */
    protected function resolveStatus($statusDescription)
    {
        $statuses = [
            Track::STATUS_DELIVERED => [
                'Delivered',
                'Zugestellt',
            ],
            Track::STATUS_IN_TRANSIT => [
                'Item posted abroad',
                'Postaufgabe im Ausland',
                'Item ready for international transport',
                'Sendung für Auslandstransport bereit',
                'Item in process of delivery',
                'Sendung in Zustellung',
                'Item being processed in Austria',
                'Sendung in Bearbeitung Österreich',
                'Item arrived in Austria',
                'Sendung in Österreich angekommen',
                'soon ready for pick up',
                'In Kürze abholbereit',
            ],
            Track::STATUS_PICKUP => [
                'ready for pick up',
                'Sendung abholbereit',
            ],
        ];

        foreach ($statuses as $status => $needles) {
            foreach ($needles as $needle) {
                if (stripos($statusDescription, $needle) !== false) {
                    return $status;
                }
            }
        }

        return Track::STATUS_UNKNOWN;
    }
}