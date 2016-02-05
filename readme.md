# Shipment Tracker

**A simple tool to scrape the parcel tracking data for DHL, GLS, UPS, USPS, and Swiss Post Service**

[![Build Status](https://travis-ci.org/sauladam/shipment-tracker.svg?branch=master)](https://travis-ci.org/sauladam/shipment-tracker)
[![Total Downloads](https://poser.pugx.org/sauladam/shipment-tracker/downloads)](https://packagist.org/packages/sauladam/shipment-tracker)


Some parcel services give you a really hard time when it comes to registering some kind of merchant or developer account.
  All you actually want is to simply keep track of a shipment and have an eye on its status. Yes, you could keep refreshing
  the tracking pages, but sometimes you've just got better stuff to do. 
  
  So here's a tool that does this automatically, without any of the developer-account and API mumbo jumbo. It just simply scrapes the website with the tracking information and transforms the data into an easily consumable format for humans and computers. Let me show you how it works!

## Installation

Just pull this package in through composer by adding it to your `composer.json` file:

```json
{
    "require": {
        "sauladam/shipment-tracker": "~0.1"
    }
}
```

Don't forget to run 

    $ composer update

after that.

## Supported Carriers
The following carriers and languages are currently supported by this package:

- DHL (de, en)
- GLS (de, en)
- UPS (de, en)
- USPS (en)
- PostCH (Swiss Post Service) (de, en)

## Basic Usage

```php
require_once 'vendor/autoload.php';

use Sauladam\ShipmentTracker\ShipmentTracker;

$dhlTracker = ShipmentTracker::get('DHL');

$track = $dhlTracker->track('00340434127681930812');
```

And that's it. Let's check if this parcel was delivered:

```php
if($track->delivered())
{
    echo "Delivered to " . $track->getRecipient();
}
else
{
    echo "Not delivered yet, The current status is " . $track->currentStatus();
}
```

#### Possible statuses are:

- `Track::STATUS_IN_TRANSIT`
- `Track::STATUS_DELIVERED`
- `Track::STATUS_PICKUP`
- `Track::STATUS_EXCEPTION`
- `Track::STATUS_WARNING`
- `Track::STATUS_UNKNOWN`

#### So where is it right now and what's happening with it?

```php
$latestEvent = $track->latestEvent();

echo "The parcel was last seen in " . $latestEvent->getLocation() . " on " . $latestEvent->getDate()->format('Y-m-d');
echo "What they did: " . $latestEvent->description();
echo "The status was " . $latestEvent->getStatus();
```

You can grab an array with the whole event history with `$track->events()`. The events are sorted by date in descending order. The date is a [Carbon](https://github.com/briannesbitt/Carbon) object.

## What else?
You just want to build up the URL for the tracking website? No problem:
```php
$url = $dhlTracker->trackingUrl('00340434127681930812');
// http://nolp.dhl.de/nextt-online-public/set_identcodes.do?lang=de&idc=00340434127681930812
```
Oh, you need it to link to the english version? Sure thing:
```php
$url = $dhlTracker->trackingUrl('00340434127681930812', 'en');
// http://nolp.dhl.de/nextt-online-public/set_identcodes.do?lang=en&idc=00340434127681930812
```
"But wait, what if I need that URL with additional parameteres?" - Well, just pass them:
```php
$url = $dhlTracker->trackingUrl('00340434127681930812', en, ['zip' => '12345']);
// http://nolp.dhl.de/nextt-online-public/set_identcodes.do?lang=en&idc=00340434127681930812&zip=12345
```

## Other features
### Additional details
Tracks and Events bot can hold additional information, accessible via e.g. `$track->getAdditionalInformation('parcelShop')`. Currently, this is only interesting for GLS, where `$track->getAdditionalInformation('parcelShop')` holds the address and the opening hours of the parcel shop if the parcel was delivered there.

### Http Clients
By default, this package uses Guzzle as well as the PHP Http client (a.k.a. `file_get_contents()`). You can pass your own client if you need to, just make sure that it implements `Sauladam\ShipmentTracker\HttpClient\HttpClientInterface`, which only requires a `get()` method.

Then, you can just pass it to the factory: `$dhlTracker = ShipmentTracker::get('DHL', new MyHttpClient);`

If you pass your own client, it's used by default, but you can swap it out later if you want:

```php
$dhlTracker->useHttpClient('guzzle');
```

Currently available clients are:
- guzzle
- php
- custom (referring to the client that you've passed)


## Notes
Please keep in mind that this is just a tool to make your life easier. I do not recommend using it in a critical environment, because, due to the way it works, it can break down as soon as the tracking website where the data is pulled from changes its structure or renames/rephrases the event descriptions. So please **use it at your own risk!**

Also, the tracking data, therefore the data provided by this package, is the property of the carrier (I guess), so under no circumstances you should use it commercially (like selling it or integrating it in a commercial service). It is intended only for personal use.
