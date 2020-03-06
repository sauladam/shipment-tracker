<?php

namespace Sauladam\ShipmentTracker\Trackers;

use Carbon\Carbon;
use Sauladam\ShipmentTracker\Event;
use Sauladam\ShipmentTracker\Track;

class Fedex extends AbstractTracker
{
    protected $trackingUrl = 'https://www.fedex.com/apps/fedextrack/';

    protected $serviceEndpoint = 'https://www.fedex.com/trackingCal/track';

    /**
     * Get the contents of the given url.
     *
     * @param string $url
     *
     * @return string
     * @throws \Exception
     */
    protected function fetch($url)
    {
        try {
            return $this->getDataProvider()->client->post($this->serviceEndpoint, $this->buildRequest())
                                                   ->getBody()
                                                   ->getContents();

        } catch (\Exception $e) {
            throw new \Exception("Could not fetch tracking data for [{$this->parcelNumber}].");
        }
    }

    /**
     * @return array
     */
    protected function buildRequest()
    {
        return [
            'headers' => [
                'Accept' => 'application/json',
            ],

            'form_params' => [
                'data'   => $this->buildDataArray(),
                'action' => 'trackpackages',
            ],
        ];
    }

    /**
     * @return false|string
     */
    protected function buildDataArray()
    {
        $array = [
            'TrackPackagesRequest' => [
                'trackingInfoList' => [
                    [
                        'trackNumberInfo' => [
                            'trackingNumber' => $this->parcelNumber,
                        ]
                    ]
                ]
            ]
        ];

        return json_encode($array);
    }

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
        return $this->trackingUrl . '?tracknumbers=' . $trackingNumber;
    }

    /**
     * Build the response array.
     *
     * @param string $response
     *
     * @return \Sauladam\ShipmentTracker\Track
     */
    protected function buildResponse($response)
    {
        $contents = json_decode($response, true)['TrackPackagesResponse']['packageList'][0];

        $track = new Track;

        foreach ($contents['scanEventList'] as $scanEvent) {
            $track->addEvent(Event::fromArray([
                'location'    => $scanEvent['scanLocation'],
                'description' => $scanEvent['status'],
                'date'        => $this->getDate($scanEvent),
                'status'      => $status = $this->resolveState($scanEvent)
            ]));

            if ($status == Track::STATUS_DELIVERED && isset($contents['receivedByNm'])) {
                $track->setRecipient($contents['receivedByNm']);
            }
        }

        if (isset($contents['totalKgsWgt'])) {
            $track->addAdditionalDetails('totalKgsWgt', $contents['totalKgsWgt']);
        }

        if (isset($contents['totalLbsWgt'])) {
            $track->addAdditionalDetails('totalLbsWgt', $contents['totalLbsWgt']);
        }

        return $track->sortEvents();
    }

    /**
     * Parse the date from the given strings.
     *
     * @param array $scanEvent
     *
     * @return \Carbon\Carbon
     */
    protected function getDate($scanEvent)
    {
        return Carbon::parse(
            $this->convert("{$scanEvent['date']}T{$scanEvent['time']}{$scanEvent['gmtOffset']}")
        );
    }

    /**
     * Convert unicode characters
     *
     * @param string $string
     * @return string
     */
    protected function convert($string)
    {
        if (PHP_MAJOR_VERSION >= 7)
            return preg_replace('/(?<=\\\u)(.{4})/', '{$1}', $string);
        else
            return str_replace('\\u002d', '-', $string);
    }

    /**
     * Match a shipping status from the given short code.
     *
     * @param $status
     *
     * @return string
     */
    protected function resolveState($status)
    {
        switch ($status['statusCD']) {
            case 'PU':
            case 'OC':
            case 'AR':
            case 'DP':
            case 'OD':
                return Track::STATUS_IN_TRANSIT;
            case 'DL':
                return Track::STATUS_DELIVERED;
            default:
                return Track::STATUS_UNKNOWN;
        }
    }
}
