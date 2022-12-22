<?php

namespace Sauladam\ShipmentTracker\Trackers;

use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use Sauladam\ShipmentTracker\Event;
use Sauladam\ShipmentTracker\Track;
use Sauladam\ShipmentTracker\Utils\XmlHelpers;

class USPS extends AbstractTracker
{
    use XmlHelpers {
        getNodeValue as normalizedNodeValue;
    }

    /**
     * @var string
     */
    protected $serviceEndpoint = 'https://tools.usps.com/go/TrackConfirmAction';

    /**
     * @var string
     */
    protected $defaultDataProvider = 'php';


    /**
     * Build the url for the given tracking number.
     *
     * @param string $trackingNumber
     * @param null $language
     * @param array $params
     *
     * @return string
     */
    public function trackingUrl($trackingNumber, $language = null, $params = [])
    {
        $additionalParams = !empty($params) ? $params : $this->trackingUrlParams;

        $qry = http_build_query(array_merge([
            'qtc_tLabels1' => $trackingNumber,
        ], $additionalParams));

        return $this->serviceEndpoint . '?' . $qry;
    }


    /**
     * Build the track.
     *
     * @param string $response
     *
     * @return Track
     */
    protected function buildResponse($response)
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($response);
        $dom->preserveWhiteSpace = false;

        $domxpath = new DOMXPath($dom);

        return $this->getTrack($domxpath);
    }


    /**
     * Get the shipment status history.
     *
     * @param DOMXPath $xpath
     *
     * @return Track
     * @throws \Exception
     */
    protected function getTrack(DOMXPath $xpath)
    {
        $track = new Track;

        if($rowsContainer = $xpath->query("//div[@id='trackingHistory_1']//div[contains(@class,'panel-actions-content')]")->item(0)){

            $items = [];
            $index = 0;

            foreach ($rowsContainer->childNodes as $child) {
                if (isset($child->tagName) && $child->tagName == 'h3') {
                    continue;
                }

                if (isset($child->tagName) && $child->tagName == 'hr') {
                    $index++;
                    continue;
                }

                $value = $this->getNodeValue($child);

                if (!empty($value)) {
                    $items[$index][] = $value;
                }
            }

            $realEvents = array_filter($items, function ($eventRows) {
                // filter only those data-portions that are at least 3 lines long, i.e. contain the date,
                // the location and a description. Otherwise it's not a real event, maybe just a short
                // info text like "Inbound Into Customs" - not sure where to put that, so just leave it alone.
                return count($eventRows) >= 3;
            });

            foreach ($realEvents as $eventData) {
                $track->addEvent(Event::fromArray([
                    'date' => $this->getDate($eventData[0]),
                    'description' => $eventData[1],
                    'location' => $eventData[2],
                    'status' => $this->resolveState($eventData[1]),
                ]));
            }

        } else if ($steps = $xpath->evaluate("//div[contains(@class,'tracking-progress-bar-status-container')]/div[contains(@class,'tb-step')][not(contains(.,'See All Tracking History'))]")) {

            foreach ($steps as $step) {
                $text = trim($xpath->evaluate('./p[normalize-space(@class)="tb-status-detail"]', $step)[0]->textContent);
                $track->addEvent(Event::fromArray([
                    'date' => trim(preg_replace('/\s+/',' ', $xpath->evaluate('./p[normalize-space(@class)="tb-date"]', $step)[0]->textContent)),
                    'description' => $text,
                    'location' => trim($xpath->evaluate('./p[normalize-space(@class)="tb-location"]', $step)[0]->textContent),
                    'status' => $this->resolveState($text),
                ]));
            }
        }

        return $track->sortEvents();
    }


    /**
     * Get the node value.
     *
     * @param DOMText|DOMNode $element
     * @param bool $withLineBreaks
     *
     * @return string
     */
    protected function getNodeValue($element, $withLineBreaks = false)
    {
        return $this->normalizedNodeValue($element, $withLineBreaks);
    }


    /**
     * Parse the date from the given string.
     *
     * @param $dateString
     *
     * @return string
     */
    protected function getDate($dateString)
    {
        // The date comes in a format like
        // November 9, 2015, 10:50 am
        return empty($dateString) ? null : Carbon::parse($dateString);
    }


    /**
     * Match a shipping status from the given description.
     *
     * @param $statusDescription
     *
     * @return string
     */
    protected function resolveState($statusDescription)
    {
        $statuses = [
            Track::STATUS_INITIAL => [
                'Shipping Label Created, USPS Awaiting Item',
            ],
            Track::STATUS_DELIVERED => [
                'Delivered',
            ],
            Track::STATUS_IN_TRANSIT => [
                'Notice Left',
                'Arrived at Unit',
                'Departed USPS Facility',
                'Arrived at USPS Facility',
                'Processed Through Sort Facility',
                'Processed Through Facility',
                'Origin Post is Preparing Shipment',
                'Acceptance',
                'Out for Delivery',
                'Sorting Complete',
                'Departed USPS Regional Facility',
                'Arrived at USPS Regional Facility',
                'Arrived at USPS Regional Origin Facility',
                'Accepted at USPS Origin Facility',
            ],
            Track::STATUS_WARNING => [],
            Track::STATUS_EXCEPTION => [],
        ];

        foreach ($statuses as $status => $needles) {
            foreach ($needles as $needle) {
                if (strpos($statusDescription, $needle) !== false) {
                    return $status;
                }
            }
        }

        return Track::STATUS_UNKNOWN;
    }
}
