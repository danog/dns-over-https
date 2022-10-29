<?php

namespace Amp\DoH\Test;

use Amp\Dns\DnsException;
use Amp\Dns\InvalidNameException;
use Amp\Dns\Record;
use Amp\Dns\Rfc1035StubResolver;
use Amp\DoH;
use Amp\DoH\Rfc8484StubResolver;
use Amp\PHPUnit\AsyncTestCase;

class Rfc8484StubResolverTest extends AsyncTestCase
{
    public function getResolver()
    {
        $DohConfig = new DoH\DoHConfig([new DoH\Nameserver('https://mozilla.cloudflare-dns.com/dns-query')]);
        return new Rfc8484StubResolver($DohConfig);
    }
    public function testResolveSecondParameterAcceptedValues()
    {
        $this->expectException(\Error::class);
        $this->getResolver()->resolve("abc.de", Record::TXT);
    }

    public function testIpAsArgumentWithIPv4Restriction()
    {
        $this->expectException(DnsException::class);
        $this->getResolver()->resolve("::1", Record::A);
    }

    public function testIpAsArgumentWithIPv6Restriction()
    {
        $this->expectException(DnsException::class);
        $this->getResolver()->resolve("127.0.0.1", Record::AAAA);
    }

    public function testInvalidName()
    {
        $this->expectException(InvalidNameException::class);
        $this->getResolver()->resolve("go@gle.com", Record::A);
    }
    public function testValidSubResolver()
    {
        $DohConfig = new DoH\DoHConfig([new DoH\Nameserver('https://mozilla.cloudflare-dns.com/dns-query')], null, new Rfc1035StubResolver());
        $this->assertInstanceOf(Rfc8484StubResolver::class, new Rfc8484StubResolver($DohConfig));
    }

    public function testInvalidNameserverFallback()
    {
        $DohConfig = new DoH\DoHConfig(
            [
                new DoH\Nameserver('https://google.com/wrong-uri'),
                new DoH\Nameserver('https://google.com/wrong-uri'),
                new DoH\Nameserver('https://nonexistant-dns.com/dns-query'),
                new DoH\Nameserver('https://mozilla.cloudflare-dns.com/dns-query'),
            ]
        );
        $resolver = new Rfc8484StubResolver($DohConfig);
        $this->assertInstanceOf(Rfc8484StubResolver::class, $resolver);
        $resolver->resolve('google.com');
    }
}
