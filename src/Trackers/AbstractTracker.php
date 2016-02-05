<?php

namespace Sauladam\ShipmentTracker\Trackers;

use Sauladam\ShipmentTracker\HttpClient\HttpClientInterface;
use Sauladam\ShipmentTracker\HttpClient\Registry;
use Sauladam\ShipmentTracker\Track;

abstract class AbstractTracker
{
    /**
     * @var Registry
     */
    protected $httpClientRegistry;

    /**
     * @var string
     */
    protected $defaultHttpClient = 'guzzle';

    /**
     * @var string
     */
    protected $language = 'en';

    /**
     * @var string
     */
    protected $parcelNumber;

    /**
     * @var array
     */
    protected $trackingUrlParams = [];

    /**
     * @var array
     */
    protected $endpointUrlParams = [];


    /**
     * Create a new tracker instance.
     *
     * @param Registry $clients
     */
    public function __construct(Registry $clients)
    {
        $this->httpClientRegistry = $clients;
    }


    /**
     * Track the given number.
     *
     * @param string      $number
     * @param string|null $language
     * @param array       $params
     *
     * @return Track
     */
    public function track($number, $language = null, $params = [])
    {
        $this->parcelNumber = $number;

        $this->extractUrlParams($params);

        if ($language) {
            $this->setLanguage($language);
        }

        $url = $this->getEndpointUrl($number, $language, $params);

        $contents = $this->fetch($url);

        return $this->buildResponse($contents);
    }


    /**
     * Set the tracking-URL and endpoint-URLs params if any were given.
     * If non of them were specified explicitly, set the same params
     * for bot URLs.
     *
     * @param $params
     */
    protected function extractUrlParams($params)
    {
        if (!array_key_exists('tracking_url', $params) && !array_key_exists('endpoint_url', $params)) {
            $this->trackingUrlParams = $this->endpointUrlParams = $params;

            return;
        }

        $this->trackingUrlParams = array_key_exists('tracking_url', $params) ? $params['tracking_url'] : [];
        $this->endpointUrlParams = array_key_exists('endpoint_url', $params) ? $params['endpoint_url'] : [];
    }


    /**
     * Set the default http client.
     *
     * @param string $name
     *
     * @return $this
     */
    public function useHttpClient($name)
    {
        $this->defaultHttpClient = $name;

        return $this;
    }


    /**
     * Get the currently set default http client.
     *
     * @return string
     */
    public function getDefaultHttpClient()
    {
        return $this->defaultHttpClient;
    }


    /**
     * Set the language iso code for the results.
     *
     * @param string $lang
     *
     * @return $this
     */
    protected function setLanguage($lang)
    {
        if (strlen($lang) !== 2) {
            $message = "Invalid language [{$lang}].";
            throw new \InvalidArgumentException($message);
        }

        $this->language = strtolower($lang);

        return $this;
    }


    /**
     * Get the http client.
     *
     * @return HttpClientInterface
     */
    protected function getClient()
    {
        return $this->httpClientRegistry->get($this->defaultHttpClient);
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
        return $this->getClient()->get($url);
    }


    /**
     * Build the endpoint url
     *
     * @param string      $trackingNumber
     * @param string|null $language
     * @param array       $params
     *
     * @return string
     */
    protected function getEndpointUrl($trackingNumber, $language = null, $params = [])
    {
        return $this->trackingUrl($trackingNumber, $language, $params);
    }


    /**
     * Build the url to the user friendly tracking site. In most
     * cases this is also the endpoint, but sometimes the tracking
     * data must be retrieved from another endpoint.
     *
     * @param string      $trackingNumber
     * @param string|null $language
     * @param array       $params
     *
     * @return string
     */
    abstract public function trackingUrl($trackingNumber, $language = null, $params = []);


    /**
     * Build the response array.
     *
     * @param string $response
     *
     * @return array
     */
    abstract protected function buildResponse($response);
}
