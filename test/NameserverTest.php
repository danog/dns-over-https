<?php

namespace Amp\DoH\Test;

use Amp\Dns\ConfigException;
use Amp\DoH\Nameserver;
use Amp\PHPUnit\TestCase;

class NameserverTest extends TestCase
{
    /**
     * @param string[] $nameservers Valid server array.
     *
     * @dataProvider provideValidServers
     */
    public function testAcceptsValidServers($nameserver, $type = Nameserver::RFC8484_POST, $headers = [])
    {
        $this->assertInstanceOf(Nameserver::class, new Nameserver($nameserver, $type, $headers));
    }

    public function provideValidServers()
    {
        return [
            ['https://mozilla.cloudflare-dns.com/dns-query'],
            ['https://mozilla.cloudflare-dns.com/dns-query', Nameserver::RFC8484_POST],
            ['https://mozilla.cloudflare-dns.com/dns-query', Nameserver::RFC8484_GET],
            ['https://mozilla.cloudflare-dns.com/dns-query', Nameserver::GOOGLE_JSON],
            ['https://dns.google/resolve', Nameserver::GOOGLE_JSON],
        ];
    }

    /**
     * @param string[] $nameservers Invalid server array.
     *
     * @dataProvider provideInvalidServers
     */
    public function testRejectsInvalidServers($nameserver, $type = Nameserver::RFC8484_POST, $headers = [])
    {
        $this->expectException(ConfigException::class);
        new Nameserver($nameserver, $type, $headers);
    }

    public function provideInvalidServers()
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
            ['http://mozilla.cloudflare-dns.com/dns-query', Nameserver::RFC8484_POST],
            ['http://mozilla.cloudflare-dns.com/dns-query', Nameserver::RFC8484_GET],
            ['http://mozilla.cloudflare-dns.com/dns-query', Nameserver::GOOGLE_JSON],
            ['http://dns.google/resolve', Nameserver::GOOGLE_JSON],

            ['mozilla.cloudflare-dns.com/dns-query'],
            ['mozilla.cloudflare-dns.com/dns-query', Nameserver::RFC8484_POST],
            ['mozilla.cloudflare-dns.com/dns-query', Nameserver::RFC8484_GET],
            ['mozilla.cloudflare-dns.com/dns-query', Nameserver::GOOGLE_JSON],
            ['dns.google/resolve', Nameserver::GOOGLE_JSON],

            ['https://mozilla.cloudflare-dns.com/dns-query', 100],
            ['https://mozilla.cloudflare-dns.com/dns-query', -1],
        ];
    }
}
