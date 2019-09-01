<?php

namespace Sauladam\ShipmentTracker\Trackers;

use Carbon\Carbon;
use GuzzleHttp\Cookie\CookieJar;
use Sauladam\ShipmentTracker\Event;
use Sauladam\ShipmentTracker\Track;

class PostCH extends AbstractTracker
{
    /**
     * @var string
     */
    protected $trackingUrl = 'https://service.post.ch/EasyTrack/submitParcelData.do';

    /**
     * @var string
     */
    protected $searchEndpoint = 'https://service.post.ch/ekp-web/api/history/not-included';

    /**
     * @var string
     */
    protected $serviceEndpoint = 'https://service.post.ch/ekp-web/api/shipment/id';

    /**
     * @var string
     */
    protected $messagesEndpoint = 'https://service.post.ch/ekp-web/core/rest/translations/{lang}/shipment-text-messages.json';

    /**
     * @var string
     */
    protected $apiUserEndpoint = 'https://service.post.ch/ekp-web/api/user';

    /**
     * @var string
     */
    protected $trackingNumberHashEndpoint = 'https://service.post.ch/ekp-web/api/history';

    /**
     * @var string
     */
    protected $language = 'de';

    /**
     * @var array
     */
    protected static $messageCodeLookup = [];

    /**
     * @var string
     */
    protected static $userId;

    /**
     * @var string
     */
    protected static $CSRFToken;

    /**
     * @var array
     */
    protected static $cookies = [];


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
            'formattedParcelCodes' => $trackingNumber,
            'lang' => $language,
        ], $additionalParams));

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
    public function getEndpointUrl($trackingNumber, $language = null, $params = [])
    {
        $this->createApiUser();

        $results = json_decode(
            $this->fetch($this->searchEndpoint . '/' . $this->getHashForTrackingNumber() . '?' . http_build_query([
                    'userId' => static::$userId,
                ]))
        );

        return $this->serviceEndpoint . '/' . $results[0]->identity . '/events';
    }


    /**
     * Build the response array.
     *
     * @param string $response
     *
     * @return Track
     */
    protected function buildResponse($response)
    {
        $this->loadMessageCodeLookup();

        return array_reduce(json_decode($response), function ($track, $event) {
            return $track->addEvent(Event::fromArray([
                'location' => empty($event->city)
                    ? ''
                    : sprintf("%s-%s %s", $event->country, $event->zip, $event->city),
                'description' => $this->getDescriptionByCode($event->eventCode),
                'date' => Carbon::parse($event->timestamp),
                'status' => $this->resolveStatus($event->eventCode),
            ]));
        }, new Track);
    }


    /**
     * Load the message lookup array for the current language if
     * it doesn't exist yet.
     */
    protected function loadMessageCodeLookup()
    {
        if (array_key_exists($this->language, static::$messageCodeLookup)) {
            return;
        }

        static::$messageCodeLookup[$this->language] = json_decode($this->fetch(
            str_replace('{lang}', $this->language, $this->messagesEndpoint)
        ), true);
    }


    /**
     * Create an API and get the user ID, the CSRF token and the session cookie.
     * Those are required for subsequent requests against the API.
     */
    protected function createApiUser()
    {
        if (null !== static::$userId) {
            return;
        }

        $response = $this->getDataProvider()->client->request(
            'GET', $this->apiUserEndpoint, [
                'cookies' => $jar = new CookieJar,
            ]
        );

        foreach ($jar->toArray() as $cookie) {
            static::$cookies[$cookie['Name']] = $cookie['Value'];
        }

        $user = json_decode($response->getBody()->getContents());


        if (!$user) {
            return;
        }

        static::$userId = $user->userIdentifier;
        static::$CSRFToken = $response->getHeader('X-CSRF-TOKEN');
    }


    /**
     * Get the search hash value for the tracking number. The hash will be used later
     * to get the entity id for the tracking number.
     *
     * @return mixed
     */
    protected function getHashForTrackingNumber()
    {
        $url = $this->trackingNumberHashEndpoint . '?' . http_build_query([
                'userId' => static::$userId,
            ]);

        $response = $this->getDataProvider()->client->post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-csrf-token' => static::$CSRFToken,
                'Cookie' => array_reduce(array_keys(static::$cookies), function ($string, $cookieName) {
                    return $string .= $cookieName . '=' . static::$cookies[$cookieName];
                }, ''),
            ],
            'json' => [
                'searchQuery' => $this->parcelNumber,
            ],
        ]);

        return json_decode($response->getBody()->getContents())->hash;
    }


    /**
     * Look up the description for the given event code.
     *
     * @param $code
     *
     * @return string
     */
    public function getDescriptionByCode($code)
    {
        $haystack = static::$messageCodeLookup[$this->language]['shipment-text--'];

        $pattern = $this->getRegexPattern($code);

        $matches = array_filter(array_keys($haystack), function ($key) use ($pattern) {
            return 1 === preg_match($pattern, $key);
        });

        return !empty($matches) ? $haystack[array_values($matches)[0]] : '';
    }


    /**
     * Build a regex pattern for the code so it will match the exact code or wildcards.
     * E. g. if the code is 'LETTER.*.88.912', it should also match with 'LETTER.*.*.912'
     * or 'LETTER.*.88.912.*'
     *
     * @param $code
     *
     * @return string
     */
    protected function getRegexPattern($code)
    {
        $pattern = array_reduce(explode('.', $code), function ($regex, $part) {
            if (1 === preg_match('/[a-z]+/i', $part)) {
                return $regex .= $part;
            }

            if ($part === '*') {
                return $regex .= "\.(\*|[a-z]+|-|_)";
            }

            return $regex .= "\.(\*|{$part})";
        }, '');


        return sprintf("/%s(\.\*)?/i", $pattern);
    }


    /**
     * Match a shipping status from the given event code.
     *
     * @param $eventCode
     *
     * @return string
     */
    protected function resolveStatus($eventCode)
    {
        $statuses = [
            Track::STATUS_DELIVERED => [
                'LETTER.*.88.40',
            ],
            Track::STATUS_IN_TRANSIT => [
                'LETTER.*.88.912',
                'LETTER.*.88.915',
                'LETTER.*.88.818',
                'LETTER.*.88.819',
                'LETTER.*.88.803',
                'LETTER.*.88.10',
            ],
            Track::STATUS_WARNING => [],
            Track::STATUS_EXCEPTION => [],
        ];

        foreach ($statuses as $status => $needles) {
            if (in_array($eventCode, $needles)) {
                return $status;
            }
        }

        return Track::STATUS_UNKNOWN;
    }
}
