<?php

namespace Sauladam\ShipmentTracker\Trackers;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Request;
use Sauladam\ShipmentTracker\Event;
use Sauladam\ShipmentTracker\Track;

class Dachser extends AbstractTracker
{


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
        return sprintf("http://partner.dachser.com/shp2/?wicket:interface=:5:pnlHead:frmHead:btnSearch::IActivePageBehaviorListener:0:-1&wicket:ignoreIfNotActive=true&random=0.35369399622175934&tfiSearch=%s", $trackingNumber);
    }

    /**
     * Build the response array.
     *
     * @param string $response
     *
     * @return array
     */
    protected function buildResponse($response)
    {
        $htmlContent = $this->extractResponseTextFromXml($response);

        $track = new Track();
        $event = new Event();
        $event->setDate($this->parseDate($htmlContent));
        $event->setStatus($this->parseStatus($htmlContent));
        $event->setLocation($this->parseLocation($htmlContent));

        $track->addEvent($event);
        $track->addAdditionalDetails('weight', $this->parseWeight($htmlContent));
        foreach ($this->parseDetails($htmlContent) as $key => $value )
        {
            $track->addAdditionalDetails($key, $value);
        }

        return $track->sortEvents();
    }

    private function parseStatusArray($dataString)
    {

        $re = '/<\/th> *<\/tr>(.*?)<\/tab/u';
        preg_match($re, $dataString, $matches);
        if (!$matches) {
            throw new \RuntimeException("Could not parse status");
        }

        $re = '/<td>(.*?)<\/td><td>(.*?)<\/td><td>(.*?)<\/td><td>(.*?)<\/td><td>(.*?)<\/td>/u';
        preg_match($re, $matches[1], $statusItems);
        $strippedTags = array_map(function ($element) {
            return strip_tags($element);
        }, $statusItems);

        $carbonDate = $this->extractCarbonDate($strippedTags);
        $statusArray = [
            'date' => $carbonDate,
            'via' => $strippedTags[4],
            'status' => $this->mapStatus($strippedTags[3])
        ];

        return $statusArray;

    }

    private function mapStatus($dachserStatusString) {

        $statusMap = [
            '&nbsp;Ausgang Verladeterminal' => Track::STATUS_IN_TRANSIT,
            '&nbsp;Delivered'                     => Track::STATUS_DELIVERED
        ];


        if (!isset($statusMap[$dachserStatusString])) {
            return Track::STATUS_UNKNOWN;
        }
        return $statusMap[$dachserStatusString];
    }

    /**
     * @param $htmlContent
     * @return string
     */
    private function parseStatus($htmlContent)
    {
        return $this->parseStatusArray($htmlContent)['status'];
    }

    /**
     * @param $htmlContent
     * @return Carbon
     */
    private function parseDate($htmlContent)
    {
        return $this->parseStatusArray($htmlContent)['date'];
    }

    /**
     * @param $htmlContent
     * @return string
     */
    private function parseLocation($htmlContent)
    {
        $location = $this->parseStatusArray($htmlContent)['via'];

        $location = str_replace('&nbsp;', '', $location );
        $location = trim ($location);

        return $location;
    }

    private function parseDetails($htmlContent) {

        $re = "/<td><span>NVE\\/SSCC<\\/span><\\/td><td><span>(\\d*)<\\/span><\\/td><td><span>Consignment number<\\/span><\\/td><td><span>(\\d*)<\\/span><\\/td>/u";
        preg_match($re, $htmlContent, $matches);

        return [
          'nve' => (isset($matches[1]) && is_numeric($matches[1])) ? (string)$matches[1] : null,
          'consignment_number' => (isset($matches[2]) && is_numeric($matches[2])) ? (string)$matches[2] : null
        ];

    }

    /**
     * @param $timeString string
     * @return string
     */
    private function extractTimeString($timeString)
    {
        $re = "/(\\d{2,2}:\\d{2,2})/u";
        preg_match($re, $timeString, $timeMatches);
        $time = " 00:00";
        if ($timeMatches) {
            $time = " " . $timeMatches[1];
            return $time;
        }
        return $time;
    }


    /**
     * Parses the approximate shipment weight
     *
     * @param $htmlContent
     * @return int|null
     */
    private function parseWeight($htmlContent)
    {

        $weight = null;
        $re = '/<td><span>(Weight|Gewicht)<\/span><\/td>.*?<td><span>(\d+) kg/';

        if (preg_match($re, $htmlContent, $weightMatches)) {
            $weight = (int)$weightMatches[2];
        }

        return $weight;
    }

    /**
     * @param $response
     * @return string
     */
    protected function extractResponseTextFromXml($response)
    {
        $dom = new \DOMDocument();
        $dom->loadXML($response);
        $htmlContent = '';
        $components = $dom->getElementsByTagName('component');
        foreach ($components as $component) {
            /** @var \DOMElement $component */
            if ($component->hasAttribute('id')) {
                if ($component->getAttribute('id') == 'ide') {
                    $htmlContent .= $component->textContent;
                }
            }
        }
        // Remove new lines to allow simpler regex patterns:
        $htmlContent = preg_replace('/[\t\r\n]/', '', $htmlContent);

        return $htmlContent;
    }

    /**
     * @param $strippedTags
     * @return static
     */
    private function extractCarbonDate($strippedTags)
    {
        $time = $this->extractTimeString($strippedTags[2]);
        if (strpos($strippedTags[1], '/')) {
            $parsedDate = Carbon::createFromFormat('m/d/Y H:i:s', $strippedTags[1] . $time . ":00");
            return $parsedDate;
        } else {
            $parsedDate = Carbon::createFromFormat('d.m.Y H:i:s', $strippedTags[1] . $time . ":00");
            return $parsedDate;
        }
    }

    /**
     * Get the contents of the given url.
     *
     * @param string $url
     *
     * @return string
     */
    protected function fetch($url)
    {
        return $this->getDataProvider()->request(new Request('POST', $url));
    }
}