<?php declare(strict_types=1);

use Amp\Dns\DnsRecord;

require __DIR__ . "/../vendor/autoload.php";

/**
 * @param array<DnsRecord> $records
 */
function pretty_print_records(string $queryName, array $records): void
{
    print "---------- " . $queryName . " " . str_repeat("-", 55 - strlen($queryName)) . " TTL --\r\n";

    $format = "%-10s %-56s %-5d\r\n";

    foreach ($records as $record) {
        print sprintf($format, DnsRecord::getName($record->getType()), $record->getValue(), $record->getTtl() ?? 0);
    }
}

function pretty_print_error(string $queryName, \Throwable $error): void
{
    print "-- " . $queryName . " " . str_repeat("-", 70 - strlen($queryName)) . "\r\n";
    print get_class($error) . ": " . $error->getMessage() . "\r\n";
}
