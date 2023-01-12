<?php declare(strict_types=1);

namespace Amp\DoH\Test;

use Amp\Dns\DnsConfigException;
use Amp\DoH\Nameserver;
use Amp\DoH\NameserverType;
use Amp\PHPUnit\AsyncTestCase;

/** @psalm-suppress PropertyNotSetInConstructor */
class NameserverTest extends AsyncTestCase
{
    /**
     * @dataProvider provideValidServers
     */
    public function testAcceptsValidServers(string $nameserver, NameserverType $type = NameserverType::RFC8484_POST, array $headers = []): void
    {
        $this->assertInstanceOf(Nameserver::class, new Nameserver($nameserver, $type, $headers));
    }

    /**
     * @return list<list{0: string, 1?: NameserverType::RFC8484_POST}>
     */
    public function provideValidServers()
    {
        return [
            ['https://mozilla.cloudflare-dns.com/dns-query'],
            ['https://mozilla.cloudflare-dns.com/dns-query', NameserverType::RFC8484_POST],
            ['https://mozilla.cloudflare-dns.com/dns-query', NameserverType::RFC8484_GET],
            ['https://mozilla.cloudflare-dns.com/dns-query', NameserverType::GOOGLE_JSON],
            ['https://dns.google/resolve', NameserverType::GOOGLE_JSON],
        ];
    }

    /**
     * @dataProvider provideInvalidServers
     */
    public function testRejectsInvalidServers(string $nameserver, NameserverType $type = NameserverType::RFC8484_POST, array $headers = []): void
    {
        $this->expectException(DnsConfigException::class);
        new Nameserver($nameserver, $type, $headers);
    }

    /**
     * @return list<list{0: string, 1?: NameserverType::RFC8484_POST}>
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
            ['http://mozilla.cloudflare-dns.com/dns-query', NameserverType::RFC8484_POST],
            ['http://mozilla.cloudflare-dns.com/dns-query', NameserverType::RFC8484_GET],
            ['http://mozilla.cloudflare-dns.com/dns-query', NameserverType::GOOGLE_JSON],
            ['http://dns.google/resolve', NameserverType::GOOGLE_JSON],

            ['mozilla.cloudflare-dns.com/dns-query'],
            ['mozilla.cloudflare-dns.com/dns-query', NameserverType::RFC8484_POST],
            ['mozilla.cloudflare-dns.com/dns-query', NameserverType::RFC8484_GET],
            ['mozilla.cloudflare-dns.com/dns-query', NameserverType::GOOGLE_JSON],
            ['dns.google/resolve', NameserverType::GOOGLE_JSON],
        ];
    }
}
