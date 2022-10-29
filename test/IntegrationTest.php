<?php

namespace Amp\DoH\Test;

use Amp\Dns;
use Amp\Dns\Record;
use Amp\DoH;
use Amp\DoH\Nameserver;
use Amp\DoH\NameserverType;
use Amp\PHPUnit\AsyncTestCase;

use function Amp\delay;

class IntegrationTest extends AsyncTestCase
{
    /**
     * @param string $hostname
     * @group internet
     * @dataProvider provideServersAndHostnames
     */
    public function testResolve($hostname, $nameservers)
    {
        foreach ($nameservers as &$nameserver) {
            $nameserver = new Nameserver(...$nameserver);
        }
        $DohConfig = new DoH\DoHConfig($nameservers);
        Dns\resolver(new DoH\Rfc8484StubResolver($DohConfig));
        $result = Dns\resolve($hostname);

        /** @var Record $record */
        $record = $result[0];
        $inAddr = @\inet_pton($record->getValue());
        $this->assertNotFalse(
            $inAddr,
            "Server name $hostname did not resolve to a valid IP address"
        );
        //\usleep(500*1000);
    }

    /**
     * @group internet
     * @dataProvider provideServers
     */
    public function testWorksAfterConfigReload($nameservers)
    {
        foreach ($nameservers as &$nameserver) {
            $nameserver = new Nameserver(...$nameserver);
        }
        $DohConfig = new DoH\DoHConfig($nameservers);
        Dns\resolver(new DoH\Rfc8484StubResolver($DohConfig));

        Dns\resolve('google.com');
        $this->assertNull(Dns\resolver()->reloadConfig());
        delay(0.5);
        $result = is_array(Dns\resolve('google.com'));
        $this->assertTrue($result);
        //\usleep(500*1000);
    }

    /**
     * @group internet
     * @dataProvider provideServers
     */
    public function testResolveIPv4only($nameservers)
    {
        foreach ($nameservers as &$nameserver) {
            $nameserver = new Nameserver(...$nameserver);
        }
        $DohConfig = new DoH\DoHConfig($nameservers);
        Dns\resolver(new DoH\Rfc8484StubResolver($DohConfig));

        $records = Dns\resolve("google.com", Record::A);

        /** @var Record $record */
        foreach ($records as $record) {
            $this->assertSame(Record::A, $record->getType());
            $inAddr = @\inet_pton($record->getValue());
            $this->assertNotFalse(
                $inAddr,
                "Server name google.com did not resolve to a valid IP address"
            );
        }
        //\usleep(500*1000);
    }

    /**
     * @group internet
     * @dataProvider provideServers
     */
    public function testResolveIPv6only($nameservers)
    {
        foreach ($nameservers as &$nameserver) {
            $nameserver = new Nameserver(...$nameserver);
        }
        $DohConfig = new DoH\DoHConfig($nameservers);
        Dns\resolver(new DoH\Rfc8484StubResolver($DohConfig));

        $records = Dns\resolve("google.com", Record::AAAA);

        /** @var Record $record */
        foreach ($records as $record) {
            $this->assertSame(Record::AAAA, $record->getType());
            $inAddr = @\inet_pton($record->getValue());
            $this->assertNotFalse(
                $inAddr,
                "Server name google.com did not resolve to a valid IP address"
            );
        }
        //\usleep(500*1000);
    }

    /**
     * @group internet
     * @dataProvider provideServers
     */
    public function testPtrLookup($nameservers)
    {
        foreach ($nameservers as &$nameserver) {
            $nameserver = new Nameserver(...$nameserver);
        }
        $DohConfig = new DoH\DoHConfig($nameservers);
        Dns\resolver(new DoH\Rfc8484StubResolver($DohConfig));

        $result = Dns\query("8.8.4.4", Record::PTR);

        /** @var Record $record */
        $record = $result[0];
        $this->assertSame("dns.google", $record->getValue());
        $this->assertNotNull($record->getTtl());
        $this->assertSame(Record::PTR, $record->getType());
        //\usleep(500*1000);
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
        $nameservers = [
            ['https://mozilla.cloudflare-dns.com/dns-query'],
            ['https://mozilla.cloudflare-dns.com/dns-query', NameserverType::RFC8484_POST],
            ['https://mozilla.cloudflare-dns.com/dns-query', NameserverType::RFC8484_GET],
            ['https://mozilla.cloudflare-dns.com/dns-query', NameserverType::GOOGLE_JSON],
            ['https://dns.google/resolve', NameserverType::GOOGLE_JSON],
        ];
        $result = [];
        for ($start = 0; $start < \count($nameservers); $start++) {
            $temp = [];
            for ($i = 0; $i < \count($nameservers); $i++) {
                $i = ($start + $i) % \count($nameservers);

                $temp[] = $nameservers[$i];
            }
            $result[] = [$temp];
        }

        return $result;
    }
}
