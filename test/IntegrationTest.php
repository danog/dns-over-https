<?php

namespace Amp\DoH\Test;

use Amp\Delayed;
use Amp\Dns;
use Amp\Dns\Record;
use Amp\DoH;
use Amp\DoH\Nameserver;
use Amp\Loop;
use Amp\PHPUnit\TestCase;

class IntegrationTest extends TestCase
{
    /**
     * @param string $hostname
     * @param \Amp\DoH\Nameserver $nameserver
     * @group internet
     * @dataProvider provideServersAndHostnames
     */
    public function testResolve($hostname, $nameserver)
    {
        $nameserver = new Nameserver(...$nameserver);
        Loop::run(function () use ($hostname, $nameserver) {
            $DohConfig = new DoH\DoHConfig([$nameserver]);
            Dns\resolver(new DoH\Rfc8484StubResolver($DohConfig));
            $result = yield Dns\resolve($hostname);

            /** @var Record $record */
            $record = $result[0];
            $inAddr = @\inet_pton($record->getValue());
            $this->assertNotFalse(
                $inAddr,
                "Server name $hostname did not resolve to a valid IP address"
            );
        });
        \usleep(500*1000);
    }

    /**
     * @group internet
     * @dataProvider provideServers
     */
    public function testWorksAfterConfigReload($nameserver)
    {
        $nameserver = new Nameserver(...$nameserver);
        Loop::run(function () use ($nameserver) {
            $DohConfig = new DoH\DoHConfig([$nameserver]);
            Dns\resolver(new DoH\Rfc8484StubResolver($DohConfig));

            yield Dns\resolve('google.com');
            $this->assertNull(yield Dns\resolver()->reloadConfig());
            yield new Delayed(500);
            $this->assertInternalType("array", yield Dns\resolve('google.com'));
        });
        \usleep(500*1000);
    }

    /**
     * @group internet
     * @dataProvider provideServers
     */
    public function testResolveIPv4only($nameserver)
    {
        $nameserver = new Nameserver(...$nameserver);
        Loop::run(function () use ($nameserver) {
            $DohConfig = new DoH\DoHConfig([$nameserver]);
            Dns\resolver(new DoH\Rfc8484StubResolver($DohConfig));

            $records = yield Dns\resolve("google.com", Record::A);

            /** @var Record $record */
            foreach ($records as $record) {
                $this->assertSame(Record::A, $record->getType());
                $inAddr = @\inet_pton($record->getValue());
                $this->assertNotFalse(
                    $inAddr,
                    "Server name google.com did not resolve to a valid IP address"
                );
            }
        });
        \usleep(500*1000);
    }

    /**
     * @group internet
     * @dataProvider provideServers
     */
    public function testResolveIPv6only($nameserver)
    {
        $nameserver = new Nameserver(...$nameserver);
        Loop::run(function () use ($nameserver) {
            $DohConfig = new DoH\DoHConfig([$nameserver]);
            Dns\resolver(new DoH\Rfc8484StubResolver($DohConfig));

            $records = yield Dns\resolve("google.com", Record::AAAA);

            /** @var Record $record */
            foreach ($records as $record) {
                $this->assertSame(Record::AAAA, $record->getType());
                $inAddr = @\inet_pton($record->getValue());
                $this->assertNotFalse(
                    $inAddr,
                    "Server name google.com did not resolve to a valid IP address"
                );
            }
        });
        \usleep(500*1000);
    }

    /**
     * @group internet
     * @dataProvider provideServers
     */
    public function testPtrLookup($nameserver)
    {
        $nameserver = new Nameserver(...$nameserver);
        Loop::run(function () use ($nameserver) {
            $DohConfig = new DoH\DoHConfig([$nameserver]);
            Dns\resolver(new DoH\Rfc8484StubResolver($DohConfig));

            $result = yield Dns\query("8.8.4.4", Record::PTR);

            /** @var Record $record */
            $record = $result[0];
            $this->assertSame("google-public-dns-b.google.com", $record->getValue());
            $this->assertNotNull($record->getTtl());
            $this->assertSame(Record::PTR, $record->getType());
        });
        \usleep(500*1000);
    }

    /**
     * Test that two concurrent requests to the same resource share the same request and do not result in two requests
     * being sent.
     */
    public function testRequestSharing()
    {
        Loop::run(function () {
            $DohConfig = new DoH\DoHConfig([new DoH\Nameserver('https://mozilla.cloudflare-dns.com/dns-query')]);
            Dns\resolver(new DoH\Rfc8484StubResolver($DohConfig));

            $promise1 = Dns\query("example.com", Record::A);
            $promise2 = Dns\query("example.com", Record::A);

            $this->assertSame($promise1, $promise2);
            $this->assertSame(yield $promise1, yield $promise2);
        });
        \usleep(500*1000);
    }
    public function provideServersAndHostnames()
    {
        $hostnames = $this->provideHostnames();
        $servers = $this->provideServers();
        $result = [];
        foreach ($hostnames as $args) {
            $hostname = $args[0];
            foreach ($servers as $args) {
                $nameserver = $args[0];
                $result[] = [$hostname, $nameserver];
            }
        }
        return $result;
    }
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

    public function provideServers()
    {
        return [
            [['https://mozilla.cloudflare-dns.com/dns-query']],
            [['https://google.com/resolve', Nameserver::GOOGLE_JSON, ["Host" => "dns.google.com"]]],
            [['https://mozilla.cloudflare-dns.com/dns-query', Nameserver::RFC8484_GET]],
            [['https://google.com/resolve', Nameserver::GOOGLE_JSON, ["Host" => "dns.google.com"]]],
            [['https://mozilla.cloudflare-dns.com/dns-query', Nameserver::GOOGLE_JSON]],
        ];
    }
}
