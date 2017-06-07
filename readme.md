# Shipment Tracker

**A simple tool to scrape the parcel tracking data for DHL, DHL Express, GLS, UPS, USPS, and Swiss Post Service**

[![Build Status](https://travis-ci.org/sauladam/shipment-tracker.svg?branch=master)](https://travis-ci.org/sauladam/shipment-tracker)
[![Total Downloads](https://poser.pugx.org/sauladam/shipment-tracker/downloads)](https://packagist.org/packages/sauladam/shipment-tracker)


Some parcel services give you a really hard time when it comes to registering some kind of merchant or developer account.
  All you actually want is to simply keep track of a shipment and have an eye on its status. Yes, you could keep refreshing
  the tracking pages, but sometimes you've just got better stuff to do. 
  
  So here's a tool that does this automatically, without any of the developer-account and API mumbo jumbo. It just simply scrapes the website with the tracking information and transforms the data into an easily consumable format for humans and computers. Let me show you how it works!

## Installation

Just pull this package in through composer or by adding it to your `composer.json` file:

```bash
$ composer require sauladam/shipment-tracker
```

Don't forget to run 

    $ composer update

after that.

## Supported Carriers
The following carriers and languages are currently supported by this package:

- DHL (de, en)
- DHL Express (de, en) (so far only for waybill numbers, not for shipment numbers of the individual pieces)
- GLS (de, en)
- UPS (de, en)
- USPS (en)
- PostCH (Swiss Post Service) (de, en)

## Basic Usage

```php
require_once 'vendor/autoload.php';

use Sauladam\ShipmentTracker\ShipmentTracker;

$dhlTracker = ShipmentTracker::get('DHL');

/* track with the standard settings */
$track = $dhlTracker->track('00340434127681930812');
// scrapes from http://nolp.dhl.de/nextt-online-public/set_identcodes.do?lang=de&idc=00340434127681930812

/* override the standard language */
$track = $dhlTracker->track('00340434127681930812', 'en');
// scrapes from http://nolp.dhl.de/nextt-online-public/set_identcodes.do?lang=en&idc=00340434127681930812

/* pass additional params to the URL (or override the default ones) */
$track = $dhlTracker->track('00340434127681930812', 'en', ['zip' => '12345']);
// scrapes from http://nolp.dhl.de/nextt-online-public/set_identcodes.do?lang=en&idc=00340434127681930812&zip=12345
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
*"But wait, what if I need that URL with additional parameteres?"* - Well, just pass them:
```php
$url = $dhlTracker->trackingUrl('00340434127681930812', 'en', ['zip' => '12345']);
// http://nolp.dhl.de/nextt-online-public/set_identcodes.do?lang=en&idc=00340434127681930812&zip=12345
```

## Other features
### Additional details
Tracks and Events both can hold additional details, accessible via e.g. `$track->getAdditionalDetails('foo')`. Currently, this is only relevant for GLS and UPS:
- **GLS:** 
  - `$track->getAdditionalDetails('parcelShop')` gets the parcel shop details and the opening hours if the parcel was delivered to one
  
- **UPS:** 
  - `$track->getAdditionalDetails('accessPoint')` gets the address of the access point if the parcel was delivered to one
  - `$track->getAdditionalDetails('pickupDueDate')` gets the pickup due date as a Carbon instance
  
- **DHL Express (waybills):** 
  - `$track->getAdditionalDetails('pieces')` gets the tracking numbers of the individual pieces that belong to this shipment 
  - `$event->getAdditionalDetails('pieces')` gets the tracking numbers of the individual pieces to which this event applies

### Data Providers
By default, this package uses Guzzle as well as the PHP Http client (a.k.a. `file_get_contents()`) to fetch the data. You can pass your own provider if you need to, e.g. if you have the page contents chillin' somewhere in a cache. Just make sure that it implements `Sauladam\ShipmentTracker\DataProviders\DataProviderInterface`, which only requires a `get()` method.

Then, you can just pass it to the factory: `$dhlTracker = ShipmentTracker::get('DHL', new CacheDataProvider);`

If you pass your data provider, it's used by default, but you can swap it out later if you want:

```php
$dhlTracker->useDataProvider('guzzle');
```

Currently available providers are:
- guzzle
- php
- custom (referring to the provider that you've passed)


## Notes
Please keep in mind that this is just a tool to make your life easier. I do not recommend using it in a critical environment, because, due to the way it works, it can break down as soon as the tracking website where the data is pulled from changes its structure or renames/rephrases the event descriptions. So please **use it at your own risk!**

Also, there's always a chance that a status can not be resolved because the event description is not known by this package, even though the most common events should be resolved correctly.

Also, the tracking data, therefore the data provided by this package, is the property of the carrier (I guess), so under no circumstances you should use it commercially (like selling it or integrating it in a commercial service). It is intended only for personal use.
