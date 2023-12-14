<?php declare(strict_types=1);

require __DIR__ . "/_bootstrap.php";

use Amp\Dns;
use Amp\DoH;

print "Downloading top 500 domains..." . PHP_EOL;

$domains = file_get_contents("https://moz.com/top-500/download?table=top500Domains");
assert($domains !== false);
$domains = array_map(function ($line) {
    return trim(explode(",", $line)[1], '"/');
}, array_filter(explode("\n", $domains)));

// Remove "URL" header
array_shift($domains);

$DohConfig = new DoH\DoHConfig([new DoH\DoHNameserver('https://mozilla.cloudflare-dns.com/dns-query')]);
Dns\dnsResolver(new DoH\Rfc8484StubDoHResolver($DohConfig));

print "Starting sequential queries...\r\n\r\n";

$timings = [];

for ($i = 0; $i < 10; $i++) {
    $start = microtime(true);
    $domain = $domains[random_int(0, count($domains) - 1)];

    try {
        pretty_print_records($domain, Dns\resolve($domain));
    } catch (Dns\DnsException $e) {
        pretty_print_error($domain, $e);
    }

    $time = round(microtime(true) - $start, 2);
    $timings[] = $time;

    printf("%'-74s\r\n\r\n", " in " . $time . " ms");
}

$averageTime = array_sum($timings) / count($timings);

print "{$averageTime} ms for an average query." . PHP_EOL;
