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

class PostCH extends AbstractTracker
{
    use XmlHelpers {
        getNodeValue as normalizedNodeValue;
    }

    /**
     * @var string
     */
    protected $serviceEndpoint = 'https://service.post.ch/EasyTrack/submitParcelData.do';

    /**
     * @var string
     */
    protected $language = 'de';


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
        $language = $language ?: $this->language;

        $additionalParams = !empty($params) ? $params : $this->trackingUrlParams;

        $qry = http_build_query(array_merge([
            'formattedParcelCodes' => $trackingNumber,
            'lang' => $language,
        ], $additionalParams));

        return $this->serviceEndpoint . '?' . $qry;
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
        $rows = $xpath->query("//table[@class='events_view fullview_tabledata']//tbody//tr");

        if (!$rows) {
            throw new \Exception("Unable to parse Swiss Post tracking data for [{$this->parcelNumber}].");
        }

        $track = new Track;

        $lastLocation = '';

        foreach ($rows as $row) {
            $eventData = $this->parseRow($row);

            if (!empty($eventData['location'])) {
                $lastLocation = $eventData['location'];
            } else {
                $eventData['location'] = $lastLocation;
            }

            $event = Event::fromArray($eventData);

            $track->addEvent($event);
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

        $date = $time = '';
        $column = 0;

        foreach ($row->childNodes as $tableCell) {

            if ($tableCell->nodeName !== 'td') {
                continue;
            }

            $value = $this->getNodeValue($tableCell);

            switch ($column) {
                case 0:
                    $date = $value;
                    break;

                case 1:
                    $time = $value;
                    break;

                case 2:
                    $rowData['description'] = $value;
                    break;

                case 3:
                    $rowData['location'] = $value;
                    break;

                default:
                    break;
            }

            $column++;
        }

        $rowData['date'] = $this->getDate($date, $time);

        $status = $this->resolveStatus($rowData['description']);

        $rowData['status'] = $status;

        return $rowData;
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
        return preg_replace('/ITM_IMP_(.*?)\s/', '', $this->normalizedNodeValue($element, $withLineBreaks));
    }


    /**
     * Parse the date from the given string.
     *
     * @param $dateString
     * @param $timeString
     *
     * @return string
     */
    protected function getDate($dateString, $timeString)
    {
        // The date comes in a format like
        // Wed 18.07.2015
        // And the time looks something like
        // 17:26
        // So we strip all characters from the date and
        // let Carbon parse it and then convert it to the
        // standard format Y-m-d H:i:s
        $dateString = preg_replace('/[a-z]|[A-Z]|,/', '', $dateString);
        $dateString = trim($dateString);
        $timeString = trim($timeString);

        return Carbon::parse("{$dateString} {$timeString}");
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
                'Mailed',
                'Aufgabe',
                'Sorting',
                'Sortierung',
                'Postal customs clearance',
                'Im Postverzollungsprozess',
                'Handed to customs',
                'An Zoll übergeben',
                'Arrival at border point',
                'Ankunft Grenzstelle Bestimmungsland',
                'Departure from border point',
                'Abgang Grenzstelle Aufgabeland',
                'Registered for collection',
                'Zur Abholung gemeldet',
                'Arrival at delivery post office',
                'Ankunft Zustellstelle',
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
