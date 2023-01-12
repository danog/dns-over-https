<?php declare(strict_types=1);

require __DIR__ . "/_bootstrap.php";

use Amp\Dns;
use Amp\Dns\DnsRecord;
use Amp\DoH;

// Set default resolver to DNS-over-HTTPS resolver
$DohConfig = new DoH\DoHConfig([new DoH\Nameserver('https://mozilla.cloudflare-dns.com/dns-query')]);
Dns\dnsResolver(new DoH\Rfc8484StubDohResolver($DohConfig));

$ip = $argv[1] ?? "8.8.8.8";

try {
    pretty_print_records($ip, Dns\query($ip, DnsRecord::PTR));
} catch (Dns\DnsException $e) {
    pretty_print_error($ip, $e);
}
