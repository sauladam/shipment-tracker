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

class USPS extends AbstractTracker
{

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
     * @param null   $language
     * @param array  $params
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
        $rows = $xpath->query("//table[@id='tc-hits']//tbody//tr[contains(@class,'detail-wrapper')]");

        if (!$rows) {
            throw new \Exception("Unable to parse USPS tracking data for [{$this->parcelNumber}].");
        }

        $track = new Track;

        $lastLocation = '';
        $lastDate = '';

        foreach ($rows as $row) {

            $eventData = $this->parseRow($row);

            if (!empty($eventData['location'])) {
                $lastLocation = $eventData['location'];
            } else {
                $eventData['location'] = $lastLocation;
            }

            if (!empty($eventData['date'])) {
                $lastDate = $eventData['date'];
            } else {
                $eventData['date'] = $lastDate;
            }

            $track->addEvent(Event::fromArray($eventData));
        }

        return $track->sortEvents();
    }


    /**
     * Parse the row of the history table.
     *
     * @param DOMElement $row
     *
     * @return array
     */
    protected function parseRow(DOMElement $row)
    {
        $rowData = [];

        $column = 0;

        foreach ($row->childNodes as $tableCell) {

            if ($tableCell->nodeName !== 'td') {
                continue;
            }

            $value = $this->getNodeValue($tableCell);

            switch ($column) {
                case 0:
                    $rowData['date'] = $this->getDate($value);
                    break;

                case 1:
                    $rowData['description'] = $value;
                    break;

                case 2:
                    $rowData['location'] = $value;
                    break;

                default:
                    break;
            }

            $column++;
        }

        $status = $this->resolveState($rowData['description']);

        $rowData['status'] = $status;

        return $rowData;
    }


    /**
     * Get the node value.
     *
     * @param DOMText|DOMNode $element
     *
     * @return string
     */
    protected function getNodeValue($element)
    {
        $value = trim($element->nodeValue);

        $value = preg_replace('/\s\s+/', ' ', $value);

        $value = preg_replace('/\s,/', ',', $value);

        return $value;
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
        //
        // Let Carbon parse it and then convert it to the
        // standard format Y-m-d H:i:s
        return empty($dateString) ? null : Carbon::parse($dateString)->toDateTimeString();
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
            Track::STATUS_DELIVERED => [
                'Delivered',
            ],
            Track::STATUS_IN_TRANSIT => [
                'Notice Left',
                'Arrived at Unit',
                'Departed USPS Facility',
                'Arrived at USPS Facility',
                'Processed Through Sort Facility',
                'Origin Post is Preparing Shipment',
                'Acceptance',
                'Out for Delivery',
                'Sorting Complete',
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
