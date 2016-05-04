<?php
/**
 * Created by PhpStorm.
 * User: malte
 * Date: 03.05.2016
 * Time: 22:58
 */

namespace Sauladam\ShipmentTracker\Trackers;


use Carbon\Carbon;
use Sauladam\ShipmentTracker\Event;
use Sauladam\ShipmentTracker\Track;
use SimpleXMLElement;

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



        var_dump(preg_replace('/[\t\r\n]/', '', $htmlContent));

        $track = new Track();
        $event = new Event();
        $event->setDate(Carbon::create());
        $event->setStatus(Track::STATUS_IN_TRANSIT);

        $track->addEvent($event);

        return $track;
    }
}