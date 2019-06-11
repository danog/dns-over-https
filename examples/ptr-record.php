<?php

require __DIR__ . "/_bootstrap.php";

use Amp\Dns;
use Amp\DoH;
use Amp\Loop;

// Set default resolver to DNS-over-HTTPS resolver
$DohConfig = new DoH\DoHConfig([new DoH\Nameserver('https://cloudflare-dns.com/dns-query')]);
Dns\resolver(new DoH\Rfc8484StubResolver($DohConfig));

Loop::run(function () {
    $ip = "8.8.8.8";

    try {
        pretty_print_records($ip, yield Dns\query($ip, Dns\Record::PTR));
    } catch (Dns\DnsException $e) {
        pretty_print_error($ip, $e);
    }
});
