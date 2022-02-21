<?php

namespace Sauladam\ShipmentTracker\Trackers;

use Carbon\Carbon;
use Sauladam\ShipmentTracker\DataProviders\Registry;
use Sauladam\ShipmentTracker\Event;
use Sauladam\ShipmentTracker\Track;

class PostNord extends AbstractTracker
{

    /**
     * @var string
     */
    protected $trackingUrl = 'https://api2.postnord.com/rest/shipment/v5/trackandtrace/findByIdentifier.json';

    /**
     * @var string
     */
    protected $language = 'en';


    /**
     * @var string
     */
    private $apiKey='';

    /**
     * @param Registry $providers
     */
    public function __construct(Registry $providers)
    {
        parent::__construct($providers);
        $this->apiKey = getenv('POSTNORD_API_KEY');
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
        $language = $language ?: $this->language;

        $additionalParams = !empty($params) ? $params : $this->trackingUrlParams;

        $qry = http_build_query(array_merge([
            'apikey' => $this->apiKey,
            'id' => $trackingNumber,
            'locale' => $language,
        ], $additionalParams));

        return $this->trackingUrl . '?' . $qry;
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
        $contents = json_decode($response, true)['TrackingInformationResponse']['shipments'][0]['items'][0];

        $track = new Track;

        foreach ($contents['events'] as $event) {
            $location = '';
            if(array_key_exists('city', $event['location'])) {
                $location = $event['location']['city'];
            } else if (array_key_exists('displayName', $event['location'])) {
                $location = $event['location']['displayName'];
            } else {
                $location = $event['location']['country'];
            }
            $track->addEvent(Event::fromArray([
                'location'    => $location,
                'description' => $event['eventDescription'],
                'date'        => $this->getDate($event['eventTime']),
                'status'      => $status = $this->resolveState($event['status'])
            ]));
        }

        return $track->sortEvents();
    }

    private function getDate($eventTime)
    {
        return Carbon::parse($eventTime);
    }

    private function resolveState($status)
    {
        switch ($status) {
            case 'INFORMED':
            case 'EN_ROUTE':
            case 'OTHER':
                return Track::STATUS_IN_TRANSIT;
            case 'DELIVERED':
                return Track::STATUS_DELIVERED;
            case 'AVAILABLE_FOR_DELIVERY':
                return Track::STATUS_PICKUP;
            case 'DELIVERY_IMPOSSIBLE':
            default:
                return Track::STATUS_UNKNOWN;
        }
    }
}
