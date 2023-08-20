<?php declare(strict_types=1);

namespace Amp\DoH\Test;

use Amp\Dns\DnsConfigException;
use Amp\DoH\DoHNameserver;
use Amp\DoH\DoHNameserverType;
use Amp\PHPUnit\AsyncTestCase;

/** @psalm-suppress PropertyNotSetInConstructor */
class DoHNameserverTest extends AsyncTestCase
{
    /**
     * @dataProvider provideValidServers
     */
    public function testAcceptsValidServers(string $nameserver, DoHNameserverType $type = DoHNameserverType::RFC8484_POST, array $headers = []): void
    {
        $this->assertInstanceOf(DoHNameserver::class, new DoHNameserver($nameserver, $type, $headers));
    }

    /**
     * @return list<list{0: string, 1?: DoHNameserverType}>
     */
    public function provideValidServers()
    {
        return [
            ['https://mozilla.cloudflare-dns.com/dns-query'],
            ['https://mozilla.cloudflare-dns.com/dns-query', DoHNameserverType::RFC8484_POST],
            ['https://mozilla.cloudflare-dns.com/dns-query', DoHNameserverType::RFC8484_GET],
            ['https://mozilla.cloudflare-dns.com/dns-query', DoHNameserverType::GOOGLE_JSON],
            ['https://dns.google/dns-query', DoHNameserverType::RFC8484_GET],
            ['https://dns.google/dns-query', DoHNameserverType::GOOGLE_JSON],
            ['https://dns.google/resolve', DoHNameserverType::GOOGLE_JSON],
        ];
    }

    /**
     * @dataProvider provideInvalidServers
     */
    public function testRejectsInvalidServers(string $nameserver, DoHNameserverType $type = DoHNameserverType::RFC8484_POST, array $headers = []): void
    {
        $this->expectException(DnsConfigException::class);
        new DoHNameserver($nameserver, $type, $headers);
    }

    /**
     * @return list<list{0: string, 1?: DoHNameserverType}>
     */
    public function provideInvalidServers(): array
    {
        return [
            [''],
            ["foobar"],
            ["foobar.com"],
            ["127.1.1"],
            ["127.1.1.1.1"],
            ["126.0.0.5"],
            ["42"],
            ["::1"],
            ["::1:53"],
            ["[::1]:"],
            ["[::1]:76235"],
            ["[::1]:0"],
            ["[::1]:-1"],
            ["[::1:51"],
            ["[::1]:abc"],
            ['http://mozilla.cloudflare-dns.com/dns-query'],
            ['http://mozilla.cloudflare-dns.com/dns-query', DoHNameserverType::RFC8484_POST],
            ['http://mozilla.cloudflare-dns.com/dns-query', DoHNameserverType::RFC8484_GET],
            ['http://mozilla.cloudflare-dns.com/dns-query', DoHNameserverType::GOOGLE_JSON],
            ['http://dns.google/dns-query', DoHNameserverType::RFC8484_POST],
            ['http://dns.google/dns-query', DoHNameserverType::RFC8484_GET],
            ['http://dns.google/resolve', DoHNameserverType::GOOGLE_JSON],

            ['mozilla.cloudflare-dns.com/dns-query'],
            ['mozilla.cloudflare-dns.com/dns-query', DoHNameserverType::RFC8484_POST],
            ['mozilla.cloudflare-dns.com/dns-query', DoHNameserverType::RFC8484_GET],
            ['mozilla.cloudflare-dns.com/dns-query', DoHNameserverType::GOOGLE_JSON],
            ['dns.google/dns-query', DoHNameserverType::RFC8484_POST],
            ['dns.google/dns-query', DoHNameserverType::RFC8484_GET],
            ['dns.google/resolve', DoHNameserverType::GOOGLE_JSON],
        ];
    }
}
