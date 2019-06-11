<?php

require __DIR__ . "/_bootstrap.php";

use Amp\Dns;
use Amp\DoH;
use Amp\Loop;
use Amp\Promise;

$customConfigLoader = new class implements Dns\ConfigLoader {
    public function loadConfig(): Promise
    {
        return Amp\call(function () {
            $hosts = yield (new Dns\HostLoader)->loadHosts();

            return new Dns\Config([
                "8.8.8.8:53",
                "[2001:4860:4860::8888]:53",
            ], $hosts, $timeout = 5000, $attempts = 3);
        });
    }
};

// Set default resolver to DNS-over-https resolver
$DohConfig = new DoH\DoHConfig([new DoH\Nameserver('https://mozilla.cloudflare-dns.com/dns-query')], null, null, $customConfigLoader);
Dns\resolver(new DoH\Rfc8484StubResolver($DohConfig));

Loop::run(function () {
    $hostname = "amphp.org";

    try {
        pretty_print_records($hostname, yield Dns\resolve($hostname));
    } catch (Dns\DnsException $e) {
        pretty_print_error($hostname, $e);
    }
});
