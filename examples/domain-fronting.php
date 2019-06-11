<?php

require __DIR__ . "/_bootstrap.php";

use Amp\Dns;
use Amp\DoH;
use Amp\DoH\Nameserver;
use Amp\Loop;

// Set default resolver to DNS-over-https resolver
$DohConfig = new DoH\DoHConfig([new DoH\Nameserver('https://google.com/resolve', Nameserver::GOOGLE_JSON, ["Host" => "dns.google.com"])]);
Dns\resolver(new DoH\Rfc8484StubResolver($DohConfig));

Loop::run(function () {
    $hostname = "amphp.org";

    try {
        pretty_print_records($hostname, yield Dns\resolve($hostname));
    } catch (Dns\DnsException $e) {
        pretty_print_error($hostname, $e);
    }
});
