<?php

namespace Amp\DoH\Test;

use Amp\PHPUnit\TestCase;
use Amp\DoH\Nameserver;

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
            ['https://google.com/resolve', Nameserver::GOOGLE_JSON, ["Host" => "dns.google.com"]],
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
            [42],
            [null],
            [true],
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
            ['http://google.com/resolve', Nameserver::GOOGLE_JSON, ["Host" => "dns.google.com"]],

            ['mozilla.cloudflare-dns.com/dns-query'],
            ['mozilla.cloudflare-dns.com/dns-query', Nameserver::RFC8484_POST],
            ['mozilla.cloudflare-dns.com/dns-query', Nameserver::RFC8484_GET],
            ['mozilla.cloudflare-dns.com/dns-query', Nameserver::GOOGLE_JSON],
            ['google.com/resolve', Nameserver::GOOGLE_JSON, ["Host" => "dns.google.com"]],

            ['https://mozilla.cloudflare-dns.com/dns-query'],
            ['https://mozilla.cloudflare-dns.com/dns-query', 100],
            ['https://mozilla.cloudflare-dns.com/dns-query', "2"],
            ['https://mozilla.cloudflare-dns.com/dns-query', -1],
            ['https://mozilla.cloudflare-dns.com/dns-query', null],
            ['https://mozilla.cloudflare-dns.com/dns-query', "hi"],
            ['https://mozilla.cloudflare-dns.com/dns-query', "foobar"],

        ];
    }

}