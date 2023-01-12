<?php declare(strict_types=1);

require __DIR__ . "/_bootstrap.php";

use Amp\Dns;
use Amp\DoH;

$customConfigLoader = new class implements Dns\DnsConfigLoader {
    public function loadConfig(): Dns\DnsConfig
    {
        $hosts = (new Dns\HostLoader)->loadHosts();

        return new Dns\DnsConfig([
            "8.8.8.8:53",
            "[2001:4860:4860::8888]:53",
        ], $hosts);
    }
};

// Set default resolver to DNS-over-https resolver
$DohConfig = new DoH\DoHConfig(
    [
        new DoH\DoHNameserver('https://daniil.it/dns-query'),
        new DoH\DoHNameserver('https://mozilla.nonexistant-dns.com/dns-query'),
        new DoH\DoHNameserver('https://mozilla.cloudflare-dns.com/dns-query'), // Will fallback to this
    ],
    null,
    null,
    $customConfigLoader
);
Dns\dnsResolver(new DoH\Rfc8484StubDoHResolver($DohConfig));

$hostname = $argv[1] ?? "amphp.org";

try {
    pretty_print_records($hostname, Dns\resolve($hostname));
} catch (Dns\DnsException $e) {
    pretty_print_error($hostname, $e);
}
