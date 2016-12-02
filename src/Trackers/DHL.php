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

class DHL extends AbstractTracker
{
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
        $rows = $xpath->query("//div[@id='pieceEvents0']/div/div/div/table/tbody/tr");

        if (!$rows) {
            throw new \Exception("Unable to parse DHL tracking data for [{$this->parcelNumber}].");
        }

        $track = new Track;

        foreach ($rows as $row) {
            $eventData = $this->parseEvent($row, $xpath);

            $track->addEvent(Event::fromArray($eventData));

            if (array_key_exists('recipient', $eventData)) {
                $track->setRecipient($eventData['recipient']);
            }
        }

        return $track->sortEvents();
    }


    /**
     * Parse the row of the history table.
     *
     * @param DOMElement $row
     * @param DOMXPath   $xpath
     *
     * @return array
     */
    protected function parseEvent(DOMElement $row, DOMXPath $xpath)
    {
        $rowData = [];

        foreach ($row->childNodes as $column => $tableCell) {
            $value = $this->getNodeValue($tableCell);

            // skip every other cell
            switch ($column) {
                case 0:
                    $rowData['date'] = $this->getDate($value);
                    break;

                case 2:
                    $rowData['location'] = $value;
                    break;

                case 4:
                    $rowData['description'] = $value;
                    break;

                default:
                    break;
            }
        }

        $status = $this->resolveStatus($rowData['description']);

        $rowData['status'] = $status;

        if ($status == Track::STATUS_DELIVERED && $recipient = $this->getRecipient($xpath)) {
            $rowData['recipient'] = $recipient;
        }

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

        return preg_replace('/\s\s+/', ' ', $value);
    }


    /**
     * Parse the date from the given string.
     *
     * @param string $dateString
     *
     * @return string
     */
    protected function getDate($dateString)
    {
        // The date comes in a format like
        // Sa, 18.07.16 12:21 Uhr or
        // Sat, 18.07.16 12:21 h
        // so we have to strip all characters in order to
        // let Carbon parse it and then convert it to the
        // standard format Y-m-d H:i:s
        $dateString = preg_replace('/[a-z]|[A-Z]|,/', '', $dateString);
        $dateString = trim($dateString);

        return Carbon::createFromFormat('d.m.y H:i', $dateString);
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
        $node = $xpath->query("//div[contains(@class,'parcel-details')]/dl/dd");

        if ($node && $node->length > 1) {
            return $this->getNodeValue($node->item(1));
        }

        return null;
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
                'item has been sent'
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
            ],
            Track::STATUS_WARNING => [
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
     * @param string      $trackingNumber
     * @param string|null $language
     * @param array       $params
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
