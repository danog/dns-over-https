<?php declare(strict_types=1);

namespace Amp\DoH\Test;

use Amp\Dns;
use Amp\Dns\DnsRecord;
use Amp\DoH;
use Amp\DoH\DoHNameserver;
use Amp\DoH\DoHNameserverType;
use Amp\PHPUnit\AsyncTestCase;

use function Amp\delay;
use function Amp\Dns\dnsResolver;

/** @psalm-suppress PropertyNotSetInConstructor */
class IntegrationTest extends AsyncTestCase
{
    /**
     * @param non-empty-list<list{0: string, 1?: DoHNameserverType}> $nameservers
     * @group internet
     * @dataProvider provideServersAndHostnames
     */
    public function testResolve(string $hostname, array $nameservers): void
    {
        $nameservers = \array_map(fn (array $v) => new DoHNameserver(...$v), $nameservers);
        $DohConfig = new DoH\DoHConfig($nameservers);
        dnsResolver(new DoH\Rfc8484StubDoHResolver($DohConfig));
        $result = Dns\resolve($hostname);

        /** @var DnsRecord $record */
        $record = $result[0];
        $inAddr = @\inet_pton($record->getValue());
        $this->assertNotFalse(
            $inAddr,
            "Server name $hostname did not resolve to a valid IP address"
        );
        \usleep(500*1000);
    }

    /**
     * @param non-empty-list<list{0: string, 1?: DoHNameserverType}> $nameservers
     * @group internet
     * @dataProvider provideServers
     */
    public function testWorksAfterConfigReload($nameservers): void
    {
        $nameservers = \array_map(fn (array $v) => new DoHNameserver(...$v), $nameservers);
        $DohConfig = new DoH\DoHConfig($nameservers);
        dnsResolver(new DoH\Rfc8484StubDoHResolver($DohConfig));

        Dns\resolve('google.com');
        /** @psalm-suppress UndefinedInterfaceMethod */
        $this->assertNull(dnsResolver()->reloadConfig());
        delay(0.5);
        $this->assertIsArray(Dns\resolve('google.com'));
        \usleep(500*1000);
    }

    /**
     * @param non-empty-list<list{0: string, 1?: DoHNameserverType}> $nameservers
     * @group internet
     * @dataProvider provideServers
     */
    public function testResolveIPv4only($nameservers): void
    {
        $nameservers = \array_map(fn (array $v) => new DoHNameserver(...$v), $nameservers);
        $DohConfig = new DoH\DoHConfig($nameservers);
        dnsResolver(new DoH\Rfc8484StubDoHResolver($DohConfig));

        $records = Dns\resolve("google.com", DnsRecord::A);

        /** @var DnsRecord $record */
        foreach ($records as $record) {
            $this->assertSame(DnsRecord::A, $record->getType());
            $inAddr = @\inet_pton($record->getValue());
            $this->assertNotFalse(
                $inAddr,
                "Server name google.com did not resolve to a valid IP address"
            );
        }
        \usleep(500*1000);
    }

    /**
     * @param non-empty-list<list{0: string, 1?: DoHNameserverType}> $nameservers
     * @group internet
     * @dataProvider provideServers
     */
    public function testResolveIPv6only($nameservers): void
    {
        $nameservers = \array_map(fn (array $v) => new DoHNameserver(...$v), $nameservers);
        $DohConfig = new DoH\DoHConfig($nameservers);
        dnsResolver(new DoH\Rfc8484StubDoHResolver($DohConfig));

        $records = Dns\resolve("google.com", DnsRecord::AAAA);

        /** @var DnsRecord $record */
        foreach ($records as $record) {
            $this->assertSame(DnsRecord::AAAA, $record->getType());
            $inAddr = @\inet_pton($record->getValue());
            $this->assertNotFalse(
                $inAddr,
                "Server name google.com did not resolve to a valid IP address"
            );
        }
        \usleep(500*1000);
    }

    /**
     * @param non-empty-list<list{0: string, 1?: DoHNameserverType}> $nameservers
     * @group internet
     * @dataProvider provideServers
     */
    public function testPtrLookup($nameservers): void
    {
        $nameservers = \array_map(fn (array $v) => new DoHNameserver(...$v), $nameservers);
        $DohConfig = new DoH\DoHConfig($nameservers);
        dnsResolver(new DoH\Rfc8484StubDoHResolver($DohConfig));

        $result = Dns\query("8.8.4.4", DnsRecord::PTR);

        /** @var DnsRecord $record */
        $record = $result[0];
        $this->assertSame("dns.google", $record->getValue());
        $this->assertNotNull($record->getTtl());
        $this->assertSame(DnsRecord::PTR, $record->getType());
        \usleep(500*1000);
    }

    /**
     * @return iterable<list{string, list<list{0: string, 1?: DoHNameserverType}>}>
     */
    public function provideServersAndHostnames()
    {
        foreach ($this->provideHostnames() as [$hostname]) {
            foreach ($this->provideServers() as [$nameserver]) {
                yield [$hostname, $nameserver];
            }
        }
    }

    /**
     * @return list<list{string}>
     */
    public function provideHostnames()
    {
        return [
            ["google.com"],
            ["github.com"],
            ["stackoverflow.com"],
            ["blog.kelunik.com"], /* that's a CNAME to GH pages */
            ["localhost"],
            ["192.168.0.1"],
            ["::1"],
            ["google.com"],
            ["mozilla.cloudflare-dns.com"],
        ];
    }

    /**
     * @return iterable<int, list{list<list{0: string, 1?: DoHNameserverType}>}>
     */
    public function provideServers()
    {
        $nameservers = [
            ['https://mozilla.cloudflare-dns.com/dns-query'],
            ['https://mozilla.cloudflare-dns.com/dns-query', DoHNameserverType::RFC8484_POST],
            ['https://mozilla.cloudflare-dns.com/dns-query', DoHNameserverType::RFC8484_GET],
            ['https://mozilla.cloudflare-dns.com/dns-query', DoHNameserverType::GOOGLE_JSON],
            ['https://dns.google/resolve', DoHNameserverType::GOOGLE_JSON],
        ];
        for ($start = 0; $start < \count($nameservers); $start++) {
            $temp = [];
            for ($i = 0; $i < \count($nameservers); $i++) {
                $i = ($start + $i) % \count($nameservers);

                $temp[] = $nameservers[$i];
            }
            yield [$temp];
        }
    }
}
