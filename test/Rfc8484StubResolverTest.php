<?php

namespace Amp\DoH\Test;

use Amp\Dns\DnsException;
use Amp\Dns\InvalidNameException;
use Amp\Dns\Record;
use Amp\DoH;
use Amp\DoH\Rfc8484StubResolver;
use Amp\Loop;
use Amp\PHPUnit\TestCase;
use Amp\Dns\Rfc1035StubResolver;
use Amp\Dns\ConfigException;

class Rfc8484StubResolverTest extends TestCase
{
    public function getResolver()
    {
        $DohConfig = new DoH\DoHConfig([new DoH\Nameserver('https://mozilla.cloudflare-dns.com/dns-query')]);
        return new Rfc8484StubResolver($DohConfig);
    }
    public function testResolveSecondParameterAcceptedValues()
    {
        Loop::run(function () {
            $this->expectException(\Error::class);
            $this->getResolver()->resolve("abc.de", Record::TXT);
        });
    }

    public function testIpAsArgumentWithIPv4Restriction()
    {
        Loop::run(function () {
            $this->expectException(DnsException::class);
            yield $this->getResolver()->resolve("::1", Record::A);
        });
    }

    public function testIpAsArgumentWithIPv6Restriction()
    {
        Loop::run(function () {
            $this->expectException(DnsException::class);
            yield $this->getResolver()->resolve("127.0.0.1", Record::AAAA);
        });
    }

    public function testInvalidName()
    {
        Loop::run(function () {
            $this->expectException(InvalidNameException::class);
            yield $this->getResolver()->resolve("go@gle.com", Record::A);
        });
    }
    public function testValidSubResolver()
    {
        Loop::run(function () {
            $DohConfig = new DoH\DoHConfig([new DoH\Nameserver('https://mozilla.cloudflare-dns.com/dns-query')], null, new Rfc1035StubResolver());
            $this->assertInstanceOf(Rfc8484StubResolver::class, new Rfc8484StubResolver($DohConfig));
        });
    }
    public function testInvalidSubResolver()
    {
        Loop::run(function () {
            $DohConfig = new DoH\DoHConfig([new DoH\Nameserver('https://mozilla.cloudflare-dns.com/dns-query')]);
            $DohConfig = new DoH\DoHConfig([new DoH\Nameserver('https://mozilla.cloudflare-dns.com/dns-query')], null, new Rfc8484StubResolver($DohConfig));
            $this->expectException(ConfigException::class);
            new Rfc8484StubResolver($DohConfig);
        });
    }
}
