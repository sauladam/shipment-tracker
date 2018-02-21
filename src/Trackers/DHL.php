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
    protected $serviceEndpoint = 'http://nolp.dhl.de/nextt-online-public/set_identcodes.do';

    /**
     * @var string
     */
    protected $language = 'de';


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

        foreach ($this->termDescriptionPairs($xpath) as $dateAndLocation => $description) {
            $eventData = array_merge([
                'description' => $description,
                'status' => $status = $this->resolveStatus($description),
            ], $this->splitDateAndLocation($dateAndLocation));

            $track->addEvent(Event::fromArray($eventData));

            if ($status == Track::STATUS_DELIVERED && $recipient = $this->getRecipient($xpath)) {
                $track->setRecipient($recipient);
            }
        }

        return $track->sortEvents();
    }


    /**
     * The event details ar split into a dt/dd list where the dt is ne date and location
     * and the dd holds the description. So we have to match them.
     *
     * @param DOMXPath $xpath
     *
     * @return array
     * @throws \Exception
     */
    protected function termDescriptionPairs(DOMXPath $xpath)
    {
        $descriptionList = $xpath->query("//div[@id='events-content']//dl");

        if (!$descriptionList || $descriptionList->length === 0) {
            throw new \Exception("Unable to parse DHL tracking data for [{$this->parcelNumber}].");
        }

        $terms = [];
        $descriptions = [];

        foreach ($xpath->query("//div[@id='events-content']//dl//dt") as $term) {
            $terms[] = $this->getNodeValue($term);
        }

        foreach ($xpath->query("//div[@id='events-content']//dl//dd") as $description) {
            $descriptions[] = $this->getNodeValue($description);
        }

        return array_combine($terms, $descriptions);
    }


    /**
     * Parse the date and the location from the given string.
     *
     * @param string $string
     *
     * @return array
     */
    protected function splitDateAndLocation($string)
    {
        // The date comes in a format like
        // Sa, 18.07.16 12:21 Uhr or
        // Sat, 18.07.16 12:21 h
        // so we have to strip all characters in order to let Carbon parse it and then
        // convert it to the standard format Y-m-d H:i:s.
        // Everything after that is considered the location.

        preg_match('/^[a-z]+, (\d{2}\.\d{2}\.\d{2} \d{2}:\d{2})\s?(.+)?$/i', $string, $matches);

        return [
            'date' => Carbon::createFromFormat('d.m.y H:i', $matches[1]),
            'location' => isset($matches[2]) ? $matches[2] : '',
        ];
    }


    /**
     * Parse the recipient.
     *
     * @param DOMXPath $xpath
     *
     * @return null|string
     */
    protected function getRecipient(DOMXPath $xpath)
    {
        $recipientDetailsNode = $xpath->query("//div[contains(@class, 'recipientDetails')]");

        if (!$recipientDetailsNode || $recipientDetailsNode->length === 0) {
            return null;
        }

        $texts = [];

        foreach ($recipientDetailsNode->item(0)->childNodes as $node) {

            if ($node->nodeType !== XML_TEXT_NODE) {
                continue;
            }

            if (empty($nodeValue = $this->getNodeValue($node))) {
                continue;
            }

            $texts[] = strpos($nodeValue, ':') !== false
                ? trim(explode(':', $nodeValue)[1])
                : $nodeValue;
        }

        return empty($texts) ? false : $texts[0];
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
                'Sendung wurde zugestellt an',
                'Die Sendung wurde ausgeliefert',
                'shipment has been successfully delivered',
                'recipient has picked up the shipment from the retail outlet',
                'recipient has picked up the shipment from the PACKSTATION',
                'item has been sent',
                'delivered from the delivery depot to the recipient by simplified company delivery',
                'per vereinfachter Firmenzustellung ab Eingangspaketzentrum zugestellt',
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
                'Sendung wird ins Zielland transportiert und dort an die Zustellorganisation',
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
            ],
            Track::STATUS_PICKUP => [
                'Die Sendung liegt in der PACKSTATION',
                'Uhrzeit der Abholung kann der Benachrichtigungskarte entnommen werden',
                'earliest time when it can be picked up can be found on the notification card',
                'shipment is ready for pick-up at the PACKSTATION',
                'Sendung wird zur Abholung in die',
                'The shipment is being brought to',
            ],
            Track::STATUS_WARNING => [
                'Sendung konnte nicht zugestellt werden',
                'shipment could not be delivered',
                'attempting to obtain a new delivery address',
                'eine neue Zustelladresse für den Empf',
                'Sendung wurde fehlgeleitet und konnte nicht zugestellt werden. Die Sendung wird umadressiert und an den',
                'shipment was misrouted and could not be delivered. The shipment will be readdressed and forwarded to the recipient',
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
            ],
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

        return $this->serviceEndpoint . '?' . $qry;
    }
}
