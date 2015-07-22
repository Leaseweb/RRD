RRD
=======================

The Leaseweb\RRD package provides functionality to work with [RRD](http://oss.oetiker.ch/rrdtool/ rrdtool) files.
At the moment, It provides a Fetcher class to access data from rrd files in a easier way.

## Requirements

* PHP 5.3+
* php-rrdtool extension

## Installation

### Using composer

Add Leaseweb\RRD in your composer.json:

```js
{
    "require": {
        "leaseweb/rrd": "*",
        ...
    }
}
```


## Usage

### Fluent API

```php

<?php

require 'vendor/autoload.php';

$rrdFetcher = new \RRD\Fetcher("database.rrd");

$data = $rrdFetcher->end('now')
                   ->start('end-1h')
                   ->fetch(\RRD\Fetcher::CF_AVERAGE)
```

### From arguments

```php

<?php

require 'vendor/autoload.php';

$rrdFetcher = new \RRD\Fetcher("database.rrd");

$data = $rrdFetcher->fetchFromArgs('AVERAGE', 300, '-1d', 'start+4h');
```


## Tests

```bash
    phpunit
```