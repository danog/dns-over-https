<?php declare(strict_types=1);

namespace Amp\DoH\Test;

use Amp\Dns\DnsException;
use Amp\Dns\DnsRecord;
use Amp\Dns\InvalidNameException;
use Amp\Dns\Rfc1035StubDnsResolver;
use Amp\DoH;
use Amp\DoH\Rfc8484StubDoHResolver;
use Amp\PHPUnit\AsyncTestCase;

/** @psalm-suppress PropertyNotSetInConstructor */
class Rfc8484StubDoHResolverTest extends AsyncTestCase
{
    public function getResolver(): Rfc8484StubDoHResolver
    {
        $DohConfig = new DoH\DoHConfig([new DoH\DoHNameserver('https://mozilla.cloudflare-dns.com/dns-query')]);
        return new Rfc8484StubDoHResolver($DohConfig);
    }
    public function testResolveSecondParameterAcceptedValues(): void
    {
        $this->expectException(\Error::class);
        $this->getResolver()->resolve("abc.de", DnsRecord::TXT);
    }

    public function testIpAsArgumentWithIPv4Restriction(): void
    {
        $this->expectException(DnsException::class);
        $this->getResolver()->resolve("::1", DnsRecord::A);
    }

    public function testIpAsArgumentWithIPv6Restriction(): void
    {
        $this->expectException(DnsException::class);
        $this->getResolver()->resolve("127.0.0.1", DnsRecord::AAAA);
    }

    public function testInvalidName(): void
    {
        $this->expectException(InvalidNameException::class);
        $this->getResolver()->resolve("go@gle.com", DnsRecord::A);
    }
    public function testValidSubResolver(): void
    {
        $DohConfig = new DoH\DoHConfig([new DoH\DoHNameserver('https://mozilla.cloudflare-dns.com/dns-query')], null, new Rfc1035StubDnsResolver());
        $this->assertInstanceOf(Rfc8484StubDoHResolver::class, new Rfc8484StubDoHResolver($DohConfig));
    }

    public function testInvalidDoHNameserverFallback(): void
    {
        $DohConfig = new DoH\DoHConfig(
            [
                new DoH\DoHNameserver('https://google.com/wrong-uri'),
                new DoH\DoHNameserver('https://google.com/wrong-uri'),
                new DoH\DoHNameserver('https://nonexistant-dns.com/dns-query'),
                new DoH\DoHNameserver('https://mozilla.cloudflare-dns.com/dns-query'),
            ]
        );
        $resolver = new Rfc8484StubDoHResolver($DohConfig);
        $this->assertInstanceOf(Rfc8484StubDoHResolver::class, $resolver);
        $resolver->resolve('google.com');
    }
}
