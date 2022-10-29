# dns

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

// Set default resolver to DNS-over-HTTPS resolver
$DohConfig = new DoH\DoHConfig([new DoH\Nameserver('https://mozilla.cloudflare-dns.com/dns-query')]); // Defaults to DoH\NameserverType::RFC8484_POST
Dns\resolver(new DoH\Rfc8484StubResolver($DohConfig));

$githubIpv4 = Dns\resolve("github.com", Dns\Record::A);
pretty_print_records("github.com", $githubIpv4);

$googleIpv4 = \Amp\async(fn () => Amp\Dns\resolve("google.com", Dns\Record::A));
$googleIpv6 = \Amp\async(fn () => Amp\Dns\resolve("google.com", Dns\Record::AAAA));

$firstGoogleResult = Amp\awaitAll([$googleIpv4, $googleIpv6]);
pretty_print_records("google.com", $firstGoogleResult);

$combinedGoogleResult = Amp\Dns\resolve("google.com");
pretty_print_records("google.com", $combinedGoogleResult);

$googleMx = Amp\Dns\query("google.com", Amp\Dns\Record::MX);
pretty_print_records("google.com", $googleMx);
```
