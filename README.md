# dns

[![Build Status](https://img.shields.io/travis/danog/dns-over-https/master.svg?style=flat-square)](https://travis-ci.org/danog/dns-over-https)
[![CoverageStatus](https://img.shields.io/coveralls/danog/dns-over-https/master.svg?style=flat-square)](https://coveralls.io/github/danog/dns-over-https?branch=master)
![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

`danog/dns-over-https` provides asynchronous and secure DNS-over-HTTPS name resolution for [Amp](https://github.com/amphp/amp).  
Supports [RFC 8484](https://tools.ietf.org/html/rfc8484) POST and GET syntaxes as well as [Google's proprietary JSON DNS format](https://developers.google.com/speed/public-dns/docs/dns-over-https).  
Supports passing custom headers for [domain fronting](https://en.wikipedia.org/wiki/Domain_fronting) with google DNS.  

## Installation

```bash
composer require danog/dns-over-https
```

## Example

```php
<?php

require __DIR__ . '/examples/_bootstrap.php';

use Amp\DoH;
use Amp\Dns;
use Amp\Loop;

Loop::run(function () {
    // Set default resolver to DNS-over-HTTPS resolver
    $DohConfig = new DoH\DoHConfig([new DoH\Nameserver('https://mozilla.cloudflare-dns.com/dns-query')]); // Defaults to DoH\Nameserver::RFC8484_POST
    Dns\resolver(new DoH\Rfc8484StubResolver($DohConfig));

    $githubIpv4 = yield Dns\resolve("github.com", Dns\Record::A);
    pretty_print_records("github.com", $githubIpv4);

    $googleIpv4 = Amp\Dns\resolve("google.com", Dns\Record::A);
    $googleIpv6 = Amp\Dns\resolve("google.com", Dns\Record::AAAA);

    $firstGoogleResult = yield Amp\Promise\first([$googleIpv4, $googleIpv6]);
    pretty_print_records("google.com", $firstGoogleResult);

    $combinedGoogleResult = yield Amp\Dns\resolve("google.com");
    pretty_print_records("google.com", $combinedGoogleResult);

    $googleMx = yield Amp\Dns\query("google.com", Amp\Dns\Record::MX);
    pretty_print_records("google.com", $googleMx);
});
```
