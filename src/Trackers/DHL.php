<?php

namespace Sauladam\ShipmentTracker\Trackers;

use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Sauladam\ShipmentTracker\Event;
use Sauladam\ShipmentTracker\Track;
use Sauladam\ShipmentTracker\Utils\XmlHelpers;

class DHL extends AbstractTracker
{
    use XmlHelpers;

    /**
     * @var string
     */
    protected $serviceEndpoint = 'https://www.dhl.de/int-verfolgen/search';

    /**
     * @var string
     */
    protected $trackingUrl = 'http://nolp.dhl.de/nextt-online-public/set_identcodes.do';

    /**
     * @var string
     */
    protected $language = 'de';

    /**
     * @var object
     */
    protected $parsedJson;


    /**
     * Hook into the parent method to clear the cache before calling it.
     *
     * @param string $number
     * @param null $language
     * @param array $params
     *
     * @return Track
     */
    public function track($number, $language = null, $params = [])
    {
        $this->parsedJson = null;

        return parent::track($number, $language, $params);
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
        if ($this->defaultDataProvider !== 'guzzle') {
            return $this->getDataProvider()->get($url);
        }

        return $this->getDataProvider()->get(
            $url,
            [
                'timeout' => 5,
                'headers' => [
                    'User-Agent' => 'tracking/1.0',
                    'Accept' => 'text/html',
                ]
            ]
        );
    }


    /**
     * @param string $contents
     *
     * @return Track
     * @throws \Exception
     */
    protected function buildResponse($contents)
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($contents);
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

        foreach ($this->getEvents($xpath) as $event) {
            $track->addEvent(Event::fromArray([
                'description' => isset($event->status) ? strip_tags($event->status) : '',
                'status' => $status = isset($event->status) ? $this->resolveStatus(strip_tags($event->status)) : '',
                'date' => isset($event->datum) ? Carbon::parse($event->datum) : null,
                'location' => isset($event->ort) ? $event->ort : '',
            ]));

            if ($status == Track::STATUS_DELIVERED && $recipient = $this->getRecipient($xpath)) {
                $track->setRecipient($recipient);
            }
        }

        return $track->sortEvents();
    }


    /**
     * Get the events.
     *
     * @param DOMXPath $xpath
     * @return array
     * @throws \Exception
     */
    protected function getEvents(DOMXPath $xpath)
    {
        $progress = $this->parseJson($xpath)->sendungen[0]->sendungsdetails->sendungsverlauf;

        return $progress->fortschritt > 0
            ? (array)$progress->events
            : [];
    }


    /**
     * Parse the recipient.
     *
     * @param DOMXPath $xpath
     *
     * @return null|string
     * @throws \Exception
     */
    protected function getRecipient(DOMXPath $xpath)
    {
        $deliveryDetails = $this->parseJson($xpath)->sendungen[0]->sendungsdetails->zustellung;

        return isset($deliveryDetails->empfaenger) && isset($deliveryDetails->empfaenger->name)
            ? $deliveryDetails->empfaenger->name
            : null;
    }


    /**
     * Parse the JSON from the script tag.
     *
     * @param DOMXPath $xpath
     * @return mixed|object
     * @throws \Exception
     */
    protected function parseJson(DOMXPath $xpath)
    {
        if ($this->parsedJson) {
            return $this->parsedJson;
        }

        $scriptTags = $xpath->query("//script");

        if ($scriptTags->length < 1) {
            throw new \Exception("Unable to parse DHL tracking data for [{$this->parcelNumber}].");
        }

        $matched = preg_match(
            "/initialState: JSON\.parse\((.*)\)\,/m",
            $scriptTags->item(2)->nodeValue,
            $matches
        );

        if ($matched !== 1) {
            throw new \Exception("Unable to parse DHL tracking data for [{$this->parcelNumber}].");
        }

        return $this->parsedJson = json_decode(json_decode($matches[1]));
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
                'aus der PACKSTATION abgeholt',
                'erfolgreich zugestellt',
                'hat die Sendung in der Filiale abgeholt',
                'des Nachnahme-Betrags an den Zahlungsempf',
                'Sendung wurde zugestellt',
                'Die Sendung wurde ausgeliefert',
                'shipment has been successfully delivered',
                'recipient has picked up the shipment from the retail outlet',
                'recipient has picked up the shipment from the PACKSTATION',
                'item has been sent',
                'delivered from the delivery depot to the recipient by simplified company delivery',
                'per vereinfachter Firmenzustellung ab Eingangspaketzentrum zugestellt',
                'im Rahmen der kontaktlosen Zustellung zugestellt',
                'Pick-up was successful',
                'Sendung wurde an den Empfänger zugestellt',
                'Shipment has been delivered to the recipient',
                'Shipment delivered',
            ],
            Track::STATUS_IN_TRANSIT => [
                'in das Zustellfahrzeug geladen',
                'im Start-Paketzentrum bearbeitet',
                'im Ziel-Paketzentrum bearbeitet',
                'im Paketzentrum bearbeitet',
                'Auftragsdaten zu dieser Sendung wurden vom Absender elektronisch an DHL',
                'auf dem Weg zur PACKSTATION',
                'wird in eine PACKSTATION weitergeleitet',
                'Die Sendung wurde abgeholt',
                'im Export-Paketzentrum bearbeitet',
                'Sendung wird ins Zielland',
                'will be transported to the destination country',
                'vom Absender in der Filiale eingeliefert',
                'Sendung konnte nicht in die PACKSTATION eingestellt werden und wurde in eine Filiale',
                'Sendung konnte nicht zugestellt werden und wird jetzt zur Abholung in die Filiale/Agentur gebracht',
                'shipment has been picked up',
                'instruction data for this shipment have been provided',
                'shipment has been processed',
                'shipment has been posted by the sender',
                'hipment has been loaded onto the delivery vehicle',
                'A 2nd attempt at delivery is being made',
                'shipment is on its way to the PACKSTATION',
                'forwarded to a PACKSTATION',
                'shipment could not be delivered to the PACKSTATION and has been forwarded to a retail outlet',
                'shipment could not be delivered, and the recipient has been notified',
                'A 2nd attempt at delivery is being made',
                'Es erfolgt ein 2. Zustellversuch',
                'Sendung wurde elektronisch angekündigt',
                'sendung wurde an DHL übergeben',
                'Sendung ist in der Region des Empfängers angekommen',
                'The shipment arrived in the region of recipient',
                'Die Zustellung an einen gewünschten Ablageort/Nachbarn wurde gewählt',
                'There is a preferred location/neighbour for this item',
                'Die Sendung wurde für den Weitertransport vorbereitet',
                'The shipment was prepared for onward transport',
                'The shipment has been processed in the parcel center of origin',
                'für den Weitertransport in die Region',
                'Die Zustellung der Sendung verzögert sich',
                'The delivery of the shipment is delayed',
                'Für diese Sendung wurde eine Paketumleitung',
                'Für diese Sendung wurde ein Ablageort',
                'The recipient has selected a',
                'Die Sendung wird für die Verladung ins Zustellfahrzeug vorbereitet',
                'The shipment is being prepared for loading onto the delivery vehicle',
                'Sendung hat die DHL-Station verlassen',
                'Sendung wurde abgeholt',
                'Shipment picked up in',
                'In Auslieferung durch Kurier',
                'With delivery courier',
                'Sendung sortiert',
                'Ankunft in der DHL Zustellstation',
                'Arrival at DHL Delivery Station',
                'Ankunft in DHL Station',
                'Shipment has arrived at hub',
                'Departed Facility',
                'Arrived at sort facility in',
                'Processed at',
                'Shipment has arrived at delivery location',
                'Sendung zur Aufbewahrung in der DHL Station',
                'Die Sendung ist im Zielland/Zielgebiet eingetroffen',
                'The shipment has arrived in the destination country/destination area',
                'Die Sendung ist im Paketzentrum eingetroffen',
                'The shipment has arrived at the parcel center',
                'Die Sendung wird zur Verzollung im Zielland/Zielgebiet vorbereitet',
                'Shipment is prepared for customs clearance in the destination country/destination area',
                'Die Sendung wurde durch den Zoll im Zielland/Zielgebiet freigegeben',
                'The shipment has cleared customs in the destination country/destination area',
                'Die Sendung hat das Import-Paketzentrum im Zielland/Zielgebiet verlassen',
                'The shipment has left the import parcel center in the destination country/destination area',
                'Die Sendung wird im Zustell-Depot für die Zustellung vorbereitet',
                'The shipment is being prepared for delivery in the delivery depot',
                'The recipient was not present',
                'wurde nicht angetroffen',
            ],
            Track::STATUS_PICKUP => [
                'Die Sendung liegt in der PACKSTATION',
                'Uhrzeit der Abholung kann der Benachrichtigungskarte entnommen werden',
                'earliest time when it can be picked up can be found on the notification card',
                'shipment is ready for pick-up at the PACKSTATION',
                'Sendung wird zur Abholung in die',
                'Sendung wurde zur Abholung in die',
                'The shipment is being brought to',
                'Die Sendung liegt ab sofort in der',
                'The shipment is available for pick-up',
                'Die Sendung liegt für den Empfänger zur Abholung bereit',
                'The shipment is ready for pick-up by the recipient',
                'Die Sendung liegt zur Abholung in',
                'The shipment is ready for collection',
            ],
            Track::STATUS_WARNING => [
                'Sendung konnte nicht zugestellt werden',
                'shipment could not be delivered',
                'attempting to obtain a new delivery address',
                'eine neue Zustelladresse für den Empf',
                'Sendung wurde fehlgeleitet und konnte nicht zugestellt werden. Die Sendung wird umadressiert und an den',
                'shipment was misrouted and could not be delivered. The shipment will be readdressed and forwarded to the recipient',
                'Leider war eine Zustellung der Sendung heute nicht möglich',
                'Die Sendung wurde leider fehlgeleitet',
                'Shipment on hold',
            ],
            Track::STATUS_EXCEPTION => [
                'cksendung eingeleitet',
                'Adressfehlers konnte die Sendung nicht zugestellt',
                'nger ist unbekannt',
                'The address is incomplete',
                'ist falsch',
                'is incorrect',
                'recipient has not picked up the shipment',
                'nicht in der Filiale abgeholt',
                'The shipment is being returned',
                'Es erfolgt eine Rücksendung',
                'Zustellung der Sendung nicht möglich',
                'recipient is unknown',
                'Zustellung nicht möglich',
                'Delivery not possible',
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


    /**
     * Build the url for the given tracking number.
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

        $urlParams = array_merge([
            'lang' => $language,
            'idc' => $trackingNumber,
        ], $additionalParams);

        $qry = http_build_query($urlParams);

        return $this->trackingUrl . '?' . $qry;
    }


    /**
     * Build the endpoint url
     *
     * @param string $trackingNumber
     * @param string|null $language
     * @param array $params
     *
     * @return string
     */
    protected function getEndpointUrl($trackingNumber, $language = null, $params = [])
    {
        $language = $language ?: $this->language;

        $additionalParams = !empty($params) ? $params : $this->endpointUrlParams;

        $urlParams = array_merge([
            'lang' => $language,
            'language' => $language,
            'idc' => $trackingNumber,
            'domain' => 'de',
        ], $additionalParams);

        return $this->serviceEndpoint . '%3F' . http_build_query($urlParams);
    }
}
