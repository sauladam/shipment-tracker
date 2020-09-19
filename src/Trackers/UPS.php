<?php

namespace Sauladam\ShipmentTracker\Trackers;

use Carbon\Carbon;
use GuzzleHttp\Cookie\CookieJar;
use Sauladam\ShipmentTracker\Event;
use Sauladam\ShipmentTracker\Track;

class UPS extends AbstractTracker
{
    /** @var string */
    protected $serviceEndpoint = 'https://www.ups.com/track/api/Track/GetStatus';

    /** @var string */
    protected $descriptionLookupEndpoint = 'https://www.ups.com/track/api/WemsData/GetLookupData';

    /** @var string */
    protected $trackingUrl = 'https://www.ups.com/track';

    /** @var array */
    protected static $cookies = [];

    /** @var null|array */
    protected $descriptionLookup;

    /** @var string */
    protected $language = 'de';


    protected function fetch($url)
    {
        if (empty(static::$cookies)) {
            $this->getCookies();
        }

        try {
            $response = $this->getDataProvider()->client->post($this->serviceUrl(), [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-XSRF-TOKEN' => static::$cookies['X-XSRF-TOKEN-ST'],
                    'Cookie' => implode(';', array_map(function ($name) {
                        return $name . '=' . static::$cookies[$name];
                    }, array_keys(static::$cookies))),
                ],
                'json' => [
                    'Locale' => $this->getLanguageQueryParam($this->language),
                    'TrackingNumber' => [
                        $this->parcelNumber,
                    ],
                ],
            ])->getBody()->getContents();

            return json_decode($response, true);
        } catch (\Exception $e) {
            throw new \Exception("Could not fetch tracking data for [{$this->parcelNumber}].");
        }
    }


    protected function getCookies()
    {
        $this->getDataProvider()->client->request(
            'GET', $this->trackingUrl($this->parcelNumber), [
                'cookies' => $jar = new CookieJar,
            ]
        );

        foreach ($jar->toArray() as $cookie) {
            static::$cookies[$cookie['Name']] = $cookie['Value'];
        }
    }


    /**
     * @param $contents
     *
     * @return Track
     * @throws \Exception
     */
    protected function buildResponse($contents)
    {
        $track = new Track;

        foreach ($contents['trackDetails'][0]['shipmentProgressActivities'] as $progressActivity) {
            if (null === $progressActivity['activityScan']) {
                continue;
            }

            $track->addEvent(Event::fromArray([
                'location' => $progressActivity['location'],
                'description' => $progressActivity['activityScan'], //$this->getDescription($progressActivity),
                'date' => $this->getDate($progressActivity),
                'status' => $status = $this->resolveState($progressActivity['activityScan']),
            ]));

            if ($status == Track::STATUS_DELIVERED && isset($contents['trackDetails'][0]['receivedBy'])) {
                $track->setRecipient($contents['trackDetails'][0]['receivedBy']);
            }

            if ($status == Track::STATUS_PICKUP && isset($contents['trackDetails'][0]['upsAccessPoint'])) {
                // $track->addAdditionalDetails('accessPoint', ...);
                $track->addAdditionalDetails('pickupDueDate', $contents['trackDetails'][0]['upsAccessPoint']['pickupPackageByDate']);
            }
        }

        return $track->sortEvents();
    }


    protected function getDescription($activity)
    {
        if (!isset($activity['milestone'])) {
            return null;
        }

        if (!$this->descriptionLookup) {
            $this->loadDescriptionLookup();
        }

        return array_key_exists($activity['milestone']['name'], $this->descriptionLookup)
            ? $this->descriptionLookup[$activity['milestone']['name']]
            : null;
    }


    protected function loadDescriptionLookup()
    {
        try {
            $url = $this->descriptionLookupEndpoint
                . '?'
                . http_build_query(['loc' => $this->getLanguageQueryParam($this->language)]);

            $response = $this->getDataProvider()->get($url);

            $this->descriptionLookup = $this->extractKeysAndValues(json_decode($response, true));
        } catch (\Exception $e) {
            $this->descriptionLookup = [];
        }
    }


    protected function extractKeysAndValues($array)
    {
        return array_reduce((array)$array, function ($lookups, $value) {
            if (!is_array($value)) {
                return $lookups;
            }

            if (!array_key_exists('key', $value) && !array_key_exists('value', $value)) {
                return array_merge($lookups, $this->extractKeysAndValues($value));
            }

            $lookups[$value['key']] = $value['value'];

            return $lookups;
        }, []);
    }


    /**
     * Parse the date from the given strings.
     *
     * @param array $activity
     *
     * @return Carbon
     */
    protected function getDate($activity)
    {
        return Carbon::parse("{$activity['date']} {$activity['time']}");
    }


    protected function getStatuses()
    {
        return [
            Track::STATUS_PICKUP => [
                'UPS Access Point™ possession',
                'Beim UPS Access Point™',
                'Delivered to UPS Access Point™',
                'An UPS Access Point™ zugestellt',
            ],
            Track::STATUS_IN_TRANSIT => [
                'Auftrag verarbeitet',
                'Wird zugestellt',
                'Ready for UPS',
                'Scan',
                'Out For Delivery',
                'receiver requested a hold for a future delivery date',
                'receiver was not available at the time of the first delivery attempt',
                'war beim 1. Zustellversuch nicht anwesend',
                'Adresse wurde korrigiert und die Zustellung neu terminiert',
                'The address has been corrected',
                'A final attempt will be made',
                'ltiger Versuch erfolgt',
                'Will deliver to a nearby UPS Access Point™ for customer pick up',
                'Zustellung wird zur Abholung durch Kunden an nahem UPS Access Point™ abgegeben',
                'Customer was not available when UPS attempted delivery',
                'In Einrichtung eingetroffen',
                'Arrived at Facility',
                'In Einrichtung eingetroffen',
                'Departed from Facility',
                'Hat Einrichtung verlassen',
                'Order Processed',
                'Auftrag verarbeitet',
            ],
            Track::STATUS_WARNING => [
                'attempting to obtain a new delivery address',
                'eine neue Zustelladresse für den Empf',
                'nderung für dieses Paket ist in Bearbeitung',
                'A delivery change for this package is in progress',
                'The receiver was not available at the time of the final delivery attempt',
            ],
            Track::STATUS_EXCEPTION => [
                'Exception',
                'Adressfehlers konnte die Sendung nicht zugestellt',
                'nger ist unbekannt',
                'The address is incomplete',
                'ist falsch',
                'is incorrect',
                'ltigen Zustellversuch nicht anwesend',
                'receiver was not available at the time of the final delivery attempt',
            ],
            Track::STATUS_DELIVERED => [
                'Delivered',
                'Zugestellt',
            ],
        ];
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
        foreach ($this->getStatuses() as $status => $needles) {
            foreach ($needles as $needle) {
                if (stripos($statusDescription, $needle) !== false) {
                    return $status;
                }
            }
        }

        return Track::STATUS_UNKNOWN;
    }


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
            'loc' => $this->getLanguageQueryParam($language),
            'tracknum' => $trackingNumber,
        ], $additionalParams));

        return $this->trackingUrl . '?' . $qry;
    }


    public function serviceUrl($language = null)
    {
        $language = $language ?: $this->language;

        return $this->serviceEndpoint
            . '?'
            . http_build_query([
                'loc' => $this->getLanguageQueryParam($language),
            ]);
    }


    /**
     * Get the language value for the url query
     *
     * @param string $givenLanguage
     *
     * @return string
     */
    protected function getLanguageQueryParam($givenLanguage)
    {
        if ($givenLanguage == 'de') {
            return 'de_DE';
        }

        return 'en_US';
    }
}
