<?php

namespace Sauladam\ShipmentTracker\Trackers;

use Carbon\Carbon;
use Sauladam\ShipmentTracker\Event;
use Sauladam\ShipmentTracker\Track;

class DHLExpress extends AbstractTracker
{
    /**
     * @var string
     */
    protected $endpointUrl = 'http://www.dhl.com/shipmentTracking';

    /**
     * @var array
     */
    protected $trackingUrls = [
        'de' => 'http://www.dhl.com/en/hidden/component_library/express/local_express/dhl_de_tracking/de/sendungsverfolgung_dhlde.html',
        'en' => 'http://www.dhl.com/en/hidden/component_library/express/local_express/dhl_de_tracking/en/tracking_dhlde.html'
    ];

    /**
     * @var string
     */
    protected $language = 'de';


    /**
     * Parse the response.
     *
     * @param string $contents
     *
     * @return Track
     * @throws \Exception
     */
    protected function buildResponse($contents)
    {
        $response = $this->json($contents);

        return $this->getTrack($response);
    }


    /**
     * Get the shipment status history.
     *
     * @param object $response
     *
     * @return Track
     */
    protected function getTrack($response)
    {
        $track = new Track;

        $shipment = $response->results[0];

        foreach ($shipment->checkpoints as $checkpoint) {
            $event = new Event;

            $event->setStatus($this->resolveStatus($checkpoint->description));
            $event->setLocation($this->getLocation($checkpoint));
            $event->setDescription($checkpoint->description);
            $event->setDate($this->getDate($checkpoint));

            if (isset($checkpoint->pIds)) {
                $event->addAdditionalDetails('pieces', $checkpoint->pIds);
            }

            $track->addEvent($event);
        }

        if ($this->isDelivered($shipment)) {
            $track->setRecipient($this->getRecipient($shipment));
        }

        if (isset($shipment->pieces) && isset($shipment->pieces->pIds)) {
            $track->addAdditionalDetails('pieces', $shipment->pieces->pIds);
        }

        return $track->sortEvents();
    }


    /**
     * Get the location.
     *
     * @param object $checkpoint
     *
     * @return string
     */
    protected function getLocation($checkpoint)
    {
        return $checkpoint->location;
    }


    /**
     * Get the formatted date.
     *
     * @param object $checkpoint
     *
     * @return string
     */
    protected function getDate($checkpoint)
    {
        $date = $this->translatedDate($checkpoint->date);

        return Carbon::createFromFormat('l, F j, Y G:i', $date . $checkpoint->time);
    }


    /**
     * Replace German month names and weekday names with the English names
     * so it can easily be parsed by Carbon.
     *
     * @param string $dateString
     *
     * @return string
     */
    protected function translatedDate($dateString)
    {
        return str_replace([
            'Januar',
            'Februar',
            'März',
            'April',
            'Mai',
            'Juni',
            'Juli',
            'August',
            'Spetember',
            'Oktober',
            'November',
            'Dezember',
            'Montag',
            'Dienstag',
            'Mittwoch',
            'Donnerstag',
            'Freitag',
            'Samstag',
            'Sonntag',
        ], [
            'January',
            'February',
            'March',
            'April',
            'May',
            'June',
            'July',
            'August',
            'September',
            'October',
            'November',
            'December',
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday',
        ], $dateString);
    }


    /**
     * Check if the shipment was delivered.
     *
     * @param $trackingInformation
     *
     * @return bool
     */
    protected function isDelivered($trackingInformation)
    {
        return isset($trackingInformation->delivery)
            && isset($trackingInformation->delivery->status)
            && $trackingInformation->delivery->status == 'delivered';
    }


    /**
     * Get the recipient / person who signed the delivery.
     *
     * @param object $shipment
     *
     * @return string
     */
    protected function getRecipient($shipment)
    {
        return $shipment->signature->signatory;
    }


    /**
     * Match a shipping status from the given description.
     *
     * @param string $description
     *
     * @return string
     *
     */
    protected function resolveStatus($description)
    {
        $statuses = [
            Track::STATUS_DELIVERED => [
                'Delivered - Signed',
                'Sendung zugestellt - übernommen',
            ],
            Track::STATUS_IN_TRANSIT => [
                'With delivery courier',
                'Sendung in Zustellung',
                'Arrived at',
                'Ankunft in der',
                'Departed Facility',
                'Verlässt DHL-Niederlassung',
                'Transferred through',
                'Sendung im Transit',
                'Processed at',
                'Sendung sortiert',
                'Clearance processing',
                'Verzollung abgeschlossen',
                'Customs status updated',
                'Verzollungsstatus aktualisiert',
                'Shipment picked up',
                'Sendung abgeholt',
            ],
        ];

        foreach ($statuses as $status => $needles) {
            foreach ($needles as $needle) {
                if (strpos($description, $needle) === 0) {
                    return $status;
                }
            }
        }

        return Track::STATUS_UNKNOWN;
    }


    /**
     * Try to decode the JSON string into an object.
     *
     * @param $string
     *
     * @return object
     * @throws \Exception
     */
    protected function json($string)
    {
        $json = json_decode($string);

        if (!$json) {
            throw new \Exception("Unable to decode DHL Express Json string [$string] for [{$this->parcelNumber}].");
        }

        return $json;
    }


    /**
     * Build the user friendly url for the given tracking number.
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

        $url = array_key_exists(
            $language,
            $this->trackingUrls
        ) ? $this->trackingUrls[$language] : $this->trackingUrls['de'];

        $additionalParams = !empty($params) ? $params : $this->trackingUrlParams;

        $qry = http_build_query(array_merge([
            'AWB' => $trackingNumber,
            'brand' => 'DHL',
        ], $additionalParams));

        return $url . '?' . $qry;
    }


    /**
     * Get the endpoint url.
     *
     * @param string $trackingNumber
     * @param null $language
     * @param array $params
     *
     * @return string
     */
    protected function getEndpointUrl($trackingNumber, $language = null, $params = [])
    {
        $additionalParams = !empty($params) ? $params : $this->endpointUrlParams;

        $qry = http_build_query(array_merge([
            'AWB' => $trackingNumber,
            'languageCode' => $language ?: $this->language,
        ], $additionalParams));

        return $this->endpointUrl . '?' . $qry;
    }
}
